<?php
declare(strict_types=1);

/**
 * Controlled, resumable WooCommerce catalogue importer.
 *
 * Default is dry-run. Writes require --commit.
 * Usage:
 *   php catalogue-import.php --dry-run
 *   php catalogue-import.php --commit --limit=50
 *
 * Imports published products and product_cat terms only. It does not import
 * customers, orders, coupons, stock movements, email jobs or payment records.
 * Media paths are mapped but media files are not copied by this script.
 */
$configPath=__DIR__.'/config.local.php';
if (!is_file($configPath)) { fwrite(STDERR,"Missing migration/config.local.php. Configure source and target credentials privately.\n"); exit(2); }
$config=require $configPath;
$commit=in_array('--commit',$argv,true);
$dry=!$commit;
$limit=100;
foreach($argv as $arg) if(str_starts_with($arg,'--limit=')) $limit=max(1,min(500,(int)substr($arg,8)));
function dbConn(array $c):PDO {
 foreach(['host','database','username','password'] as $k) if(!array_key_exists($k,$c)||($k!=='password'&&$c[$k]==='')) throw new RuntimeException("Missing DB setting: {$k}");
 $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$c['host'],$c['port']??'3306',$c['database']);
 return new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function qi(string $name):string { if(!preg_match('/^[A-Za-z0-9_]+$/',$name)) throw new InvalidArgumentException('Unsafe SQL identifier'); return '`'.$name.'`'; }
function sourceMeta(PDO $src,string $meta,int $id):array {
 $q=$src->prepare("SELECT meta_key,meta_value FROM {$meta} WHERE post_id=? AND meta_key IN ('_sku','_regular_price','_sale_price','_stock_status','_stock','_manage_stock','_weight','_thumbnail_id','_tax_class','_tax_status','_virtual')");
 $q->execute([$id]); $out=[]; foreach($q->fetchAll() as $r) $out[$r['meta_key']]=$r['meta_value']; return $out;
}
function checksum(array $v):string { return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)); }
try {
 $src=dbConn($config['source']); $dst=dbConn($config['target']); $prefix=(string)($config['source']['prefix']??'wp_');
 $posts=qi($prefix.'posts'); $meta=qi($prefix.'postmeta'); $terms=qi($prefix.'terms'); $tax=qi($prefix.'term_taxonomy'); $rel=qi($prefix.'term_relationships');
 $batchId=null;
 if($commit){
  $dst->beginTransaction();
  $q=$dst->prepare("INSERT INTO migration_batches(source_name,source_snapshot_at,status,notes) VALUES('woocommerce_catalogue',?,'running',?)");
  $q->execute([$config['snapshot']??null,'Catalogue products and product categories; media files excluded.']); $batchId=(int)$dst->lastInsertId(); $dst->commit();
 }
 echo "EKHAYA CATALOGUE IMPORT | ".($dry?'DRY RUN':'COMMIT')." | limit {$limit}\n";
 // Categories: only product_cat taxonomy. Create categories in two passes to preserve parent links.
 $categoryRows=$src->query("SELECT t.term_id,t.name,t.slug,tt.description,tt.parent FROM {$terms} t JOIN {$tax} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='product_cat' ORDER BY tt.parent,t.term_id")->fetchAll();
 $catMap=[]; $catInsert=$dst->prepare("INSERT INTO categories(parent_id,name,slug,description,sort_order,is_active) VALUES(?,?,?,?,0,1)");
 $catLookup=$dst->prepare('SELECT id FROM categories WHERE slug=? LIMIT 1');
 $legacyLookup=$dst->prepare("SELECT target_id FROM legacy_id_map WHERE source_system='woocommerce' AND source_entity='category' AND source_id=? AND target_entity='category' LIMIT 1");
 $legacyInsert=$dst->prepare("INSERT INTO legacy_id_map(source_system,source_entity,source_id,target_entity,target_id,batch_id,source_checksum) VALUES('woocommerce','category',?,'category',?,?,?) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),batch_id=VALUES(batch_id),source_checksum=VALUES(source_checksum)");
 $pending=$categoryRows;
 for($pass=0;$pass<=count($categoryRows) && $pending;$pass++){
  $next=[];
  foreach($pending as $cat){
   $sid=(string)$cat['term_id']; $parent=(int)$cat['parent']; $parentId=null;
   if($parent>0){ if(!isset($catMap[$parent])){$legacyLookup->execute([(string)$parent]);$mapped=$legacyLookup->fetchColumn();if($mapped)$catMap[$parent]=(int)$mapped;} if(!isset($catMap[$parent])){$next[]=$cat;continue;} $parentId=$catMap[$parent];}
   $legacyLookup->execute([$sid]); $mapped=$legacyLookup->fetchColumn();
   if($mapped){$catMap[(int)$cat['term_id']]=(int)$mapped;continue;}
   $catLookup->execute([$cat['slug']]); $existing=$catLookup->fetchColumn();
   if($existing){$newId=(int)$existing;}
   elseif($dry){$newId=-((int)$cat['term_id']);}
   else{$catInsert->execute([$parentId,$cat['name'],$cat['slug'],$cat['description']]);$newId=(int)$dst->lastInsertId();}
   $catMap[(int)$cat['term_id']]=$newId;
   if($commit)$legacyInsert->execute([$sid,$newId,$batchId,checksum($cat)]);
  }
  $pending=$next;
 }
 if($pending) throw new RuntimeException('Category hierarchy could not be resolved. Import stopped before product processing.');
 echo "Categories found: ".count($categoryRows)."\n";
 $productQuery=$src->prepare("SELECT ID,post_title,post_name,post_content,post_excerpt,post_date FROM {$posts} WHERE post_type='product' AND post_status='publish' AND ID>? ORDER BY ID LIMIT {$limit}");
 $checkpoint=0;
 if($commit){
  $q=$dst->prepare("SELECT MAX(CAST(source_id AS UNSIGNED)) FROM legacy_id_map WHERE source_system='woocommerce' AND source_entity='product' AND target_entity='product'");
  $q->execute(); $checkpoint=(int)$q->fetchColumn();
 }
 $productQuery->execute([$checkpoint]); $products=$productQuery->fetchAll();
 $productInsert=$dst->prepare("INSERT INTO products(sku,name,slug,product_type,description,short_description,status,regular_price,sale_price,manage_stock,stock_quantity,stock_status,weight_kg,requires_shipping,source_created_at) VALUES(?,?,?,?,?,?,'published',?,?,?,?,?,?,?,?)");
 $productLookup=$dst->prepare('SELECT id FROM products WHERE slug=? OR (sku IS NOT NULL AND sku<>'' AND sku=?) LIMIT 1');
 $productMapLookup=$dst->prepare("SELECT target_id FROM legacy_id_map WHERE source_system='woocommerce' AND source_entity='product' AND source_id=? AND target_entity='product' LIMIT 1");
 $productMapInsert=$dst->prepare("INSERT INTO legacy_id_map(source_system,source_entity,source_id,target_entity,target_id,batch_id,source_checksum) VALUES('woocommerce','product',?,'product',?,?,?) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),batch_id=VALUES(batch_id),source_checksum=VALUES(source_checksum)");
 $linkInsert=$dst->prepare('INSERT IGNORE INTO product_categories(product_id,category_id) VALUES(?,?)');
 $imageInsert=$dst->prepare('INSERT INTO product_images(product_id,source_path,alt_text,sort_order,is_primary) VALUES(?,?,?,?,?)');
 $imageExists=$dst->prepare('SELECT id FROM product_images WHERE product_id=? AND source_path=? LIMIT 1');
 $variantPosts=$src->prepare("SELECT ID,post_title FROM {$posts} WHERE post_type='product_variation' AND post_status IN ('publish','private') AND post_parent=? ORDER BY menu_order,ID");
 $variantInsert=$dst->prepare('INSERT INTO product_variants(product_id,sku,option_values,regular_price,sale_price,stock_quantity,stock_status) VALUES(?,?,?,?,?,?,?)');
 $variantMapLookup=$dst->prepare("SELECT target_id FROM legacy_id_map WHERE source_system='woocommerce' AND source_entity='product_variation' AND source_id=? AND target_entity='variant' LIMIT 1");
 $variantMapInsert=$dst->prepare("INSERT INTO legacy_id_map(source_system,source_entity,source_id,target_entity,target_id,batch_id,source_checksum) VALUES('woocommerce','product_variation',?,'variant',?,?,?) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),batch_id=VALUES(batch_id),source_checksum=VALUES(source_checksum)");
 $productTypeUpdate=$dst->prepare("UPDATE products SET product_type='variable' WHERE id=? AND product_type='simple'");
 $attachmentMeta=$dst->prepare('SELECT meta_value FROM '.$meta.' WHERE post_id=? AND meta_key='_wp_attached_file' LIMIT 1');
 $mediaPath=$dst->prepare("SELECT meta_value FROM {$meta} WHERE post_id=? AND meta_key='_wp_attached_file' LIMIT 1");
 $counts=['new'=>0,'mapped'=>0,'skipped_existing'=>0,'category_links'=>0,'images_mapped'=>0]; $lastId=$checkpoint;
 foreach($products as $p){
  $id=(int)$p['ID'];$lastId=$id;$m=sourceMeta($src,$meta,$id);$sku=trim((string)($m['_sku']??''));$slug=trim((string)$p['post_name']);
  if($slug==='')$slug='product-'.$id;
  $regular=is_numeric($m['_regular_price']??null)?(float)$m['_regular_price']:0.0;
  $sale=is_numeric($m['_sale_price']??null)&&$m['_sale_price']!==''?(float)$m['_sale_price']:null;
  $stockStatus=($m['_stock_status']??'instock')==='outofstock'?'out_of_stock':((($m['_stock_status']??'instock')==='onbackorder')?'backorder':'in_stock');
  $manage=($m['_manage_stock']??'no')==='yes'?1:0;
  $stock=is_numeric($m['_stock']??null)?(float)$m['_stock']:null;
  $weight=is_numeric($m['_weight']??null)?(float)$m['_weight']:null;
  $virtual=($m['_virtual']??'no')==='yes';
  $record=['ID'=>$id,'title'=>$p['post_title'],'slug'=>$slug,'sku'=>$sku,'regular'=>$regular,'sale'=>$sale,'status'=>$stockStatus,'stock'=>$stock,'date'=>$p['post_date']];
  $variantPosts->execute([$id]);$sourceVariants=$variantPosts->fetchAll();
  $productMapLookup->execute([(string)$id]);$mapped=$productMapLookup->fetchColumn();
  if($mapped){$counts['mapped']++;continue;}
  $productLookup->execute([$slug,$sku]);$existing=$productLookup->fetchColumn();
  if($existing){$targetId=(int)$existing;$counts['skipped_existing']++;}
  elseif($dry){$targetId=-$id;$counts['new']++;}
  else{
   $productInsert->execute([$sku!==''?$sku:null,$p['post_title'],$slug,$sourceVariants?'variable':'simple',$p['post_content'],$p['post_excerpt'],$regular,$sale,$manage,$stock,$stockStatus,$weight,$virtual?0:1,$p['post_date']]);
   $targetId=(int)$dst->lastInsertId();$counts['new']++;
  }
  if($commit){
   $productMapInsert->execute([(string)$id,$targetId,$batchId,checksum($record)]);
   $q=$src->prepare("SELECT tt.term_id FROM {$rel} tr JOIN {$tax} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=? AND tt.taxonomy='product_cat'");
   $q->execute([$id]);
   foreach($q->fetchAll(PDO::FETCH_COLUMN) as $termId){$cid=$catMap[(int)$termId]??null;if($cid && $cid>0){$linkInsert->execute([$targetId,$cid]);$counts['category_links']++;}}
   $thumb=(int)($m['_thumbnail_id']??0);
   $attachmentIds=$thumb>0?[$thumb]:[];
   $galleryQ=$src->prepare("SELECT meta_value FROM {$meta} WHERE post_id=? AND meta_key='_product_image_gallery' LIMIT 1");$galleryQ->execute([$id]);$gallery=(string)($galleryQ->fetchColumn()?:'');
   if($gallery!=='')foreach(explode(',',$gallery) as $aid){$aid=(int)$aid;if($aid>0&&!in_array($aid,$attachmentIds,true))$attachmentIds[]=$aid;}
   foreach($attachmentIds as $index=>$aid){
    $mediaPath->execute([$aid]);$relative=$mediaPath->fetchColumn();if(!$relative)continue;
    $path='uploads/'.ltrim((string)$relative,'/');
    $imageExists->execute([$targetId,$path]);if($imageExists->fetchColumn())continue;
    $imageInsert->execute([$targetId,$path,$p['post_title'],$index,$index===0?1:0]);$counts['images_mapped']++;
   }
   if($sourceVariants){
    $productTypeUpdate->execute([$targetId]);
    foreach($sourceVariants as $variant){
     $vid=(int)$variant['ID'];$variantMapLookup->execute([(string)$vid]);if($variantMapLookup->fetchColumn())continue;
     $vm=sourceMeta($src,$meta,$vid);$optionsQ=$src->prepare("SELECT meta_key,meta_value FROM {$meta} WHERE post_id=? AND meta_key LIKE 'attribute_%'");$optionsQ->execute([$vid]);
     $options=[];foreach($optionsQ->fetchAll() as $option)$options[$option['meta_key']]=$option['meta_value'];
     $vsku=trim((string)($vm['_sku']??''));$vregular=is_numeric($vm['_regular_price']??null)?(float)$vm['_regular_price']:$regular;
     $vsale=is_numeric($vm['_sale_price']??null)&&$vm['_sale_price']!==''?(float)$vm['_sale_price']:null;
     $vstock=is_numeric($vm['_stock']??null)?(float)$vm['_stock']:$stock;
     $vstatus=($vm['_stock_status']??$stockStatus);
     if($vstatus==='instock')$vstatus='in_stock';elseif($vstatus==='outofstock')$vstatus='out_of_stock';elseif($vstatus==='onbackorder')$vstatus='backorder';
     $variantInsert->execute([$targetId,$vsku!==''?$vsku:null,json_encode($options,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),$vregular,$vsale,$vstock,$vstatus]);
     $variantId=(int)$dst->lastInsertId();
     $variantMapInsert->execute([(string)$vid,$variantId,$batchId,checksum(['id'=>$vid,'sku'=>$vsku,'options'=>$options,'regular'=>$vregular,'sale'=>$vsale])]);
    }
   }
  }
 }
 if($commit){
  $dst->prepare("UPDATE migration_batches SET status='validated',completed_at=UTC_TIMESTAMP(),notes=CONCAT(COALESCE(notes,''),' | Last source product ID: ',?) WHERE id=?")->execute([(string)$lastId,$batchId]);
 }
 echo "Products processed: ".count($products)." | Last source ID: {$lastId}\n";
 foreach($counts as $k=>$v)echo ucfirst(str_replace('_',' ',$k)).": {$v}\n";
 echo "Media paths are mapped as uploads/<relative path>; files still need a separately verified copy into the staging public media directory.\n";
 echo $dry?"DRY RUN ONLY — no target writes performed. Re-run with --commit to import this batch.\n":"Batch committed. Re-run --commit to continue from the next source product ID.\n";
} catch(Throwable $e){fwrite(STDERR,"Catalogue import stopped: ".$e->getMessage()."\n");exit(1);}
