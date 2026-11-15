<?php
declare(strict_types=1);

/**
 * Read-only WooCommerce catalogue extraction preview.
 * Usage: php catalogue-preview.php [--json]
 * Does not write to either database and never reads customer/order tables.
 */
$configPath=__DIR__.'/config.local.php';
if (!is_file($configPath)) { fwrite(STDERR,"Missing migration/config.local.php. Copy config.example.php and configure private credentials.\n"); exit(2); }
$config=require $configPath;
function connectDb(array $c): PDO {
 foreach (['host','database','username','password'] as $key) {
  if (!array_key_exists($key,$c) || ($key!=='password' && $c[$key]==='')) throw new RuntimeException("Database configuration incomplete: {$key}");
 }
 $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$c['host'],$c['port']??'3306',$c['database']);
 return new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function tableName(string $prefix,string $suffix): string {
 $name=$prefix.$suffix;
 if (!preg_match('/^[A-Za-z0-9_]+$/',$name)) throw new InvalidArgumentException('Unsafe table identifier');
 return '`'.$name.'`';
}
try {
 $source=connectDb($config['source']);
 $prefix=(string)($config['source']['prefix']??'wp_');
 $posts=tableName($prefix,'posts'); $meta=tableName($prefix,'postmeta');
 $terms=tableName($prefix,'terms'); $tax=tableName($prefix,'term_taxonomy'); $rel=tableName($prefix,'term_relationships');
 $stmt=$source->query("SELECT ID,post_title,post_name,post_content,post_excerpt,post_date,post_status FROM {$posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID");
 $products=$stmt->fetchAll();
 $ids=array_map(static fn($p)=>(int)$p['ID'],$products);
 $metadata=[];
 if ($ids) {
  foreach (array_chunk($ids,400) as $chunk) {
   $marks=implode(',',array_fill(0,count($chunk),'?'));
   $q=$source->prepare("SELECT post_id,meta_key,meta_value FROM {$meta} WHERE post_id IN ({$marks}) AND meta_key IN ('_sku','_regular_price','_sale_price','_stock_status','_stock','_manage_stock','_weight','_thumbnail_id','_product_image_gallery','_virtual','_tax_status','_tax_class')");
   $q->execute($chunk);
   foreach ($q->fetchAll() as $row) $metadata[(int)$row['post_id']][$row['meta_key']]=$row['meta_value'];
  }
 }
 $categories=[];
 if ($ids) {
  foreach (array_chunk($ids,400) as $chunk) {
   $marks=implode(',',array_fill(0,count($chunk),'?'));
   $q=$source->prepare("SELECT tr.object_id,t.name,t.slug,tt.parent FROM {$rel} tr JOIN {$tax} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN {$terms} t ON t.term_id=tt.term_id WHERE tt.taxonomy='product_cat' AND tr.object_id IN ({$marks}) ORDER BY t.name");
   $q->execute($chunk);
   foreach ($q->fetchAll() as $row) $categories[(int)$row['object_id']][]=['name'=>$row['name'],'slug'=>$row['slug'],'parent_term_id'=>(int)$row['parent']];
  }
 }
 $report=['tool'=>'Ekhaya WooCommerce catalogue preview','mode'=>'read_only','generated_at_utc'=>gmdate('c'),'source_prefix'=>$prefix,'published_product_count'=>count($products),'products'=>[],'safety'=>['writes_performed'=>false,'customer_tables_read'=>false,'order_tables_read'=>false,'email_queue_touched'=>false]];
 foreach ($products as $p) {
  $id=(int)$p['ID']; $m=$metadata[$id]??[];
  $gallery=trim((string)($m['_product_image_gallery']??''));
  $galleryIds=$gallery===''?[]:array_values(array_filter(array_map('intval',explode(',',$gallery))));
  $report['products'][]=[
   'source_product_id'=>$id,'name'=>$p['post_title'],'slug'=>$p['post_name'],
   'sku'=>$m['_sku']??null,'regular_price'=>$m['_regular_price']??null,'sale_price'=>$m['_sale_price']??null,
   'stock_status'=>$m['_stock_status']??null,'stock_quantity'=>$m['_stock']??null,
   'manage_stock'=>$m['_manage_stock']??null,'weight'=>$m['_weight']??null,
   'thumbnail_attachment_id'=>isset($m['_thumbnail_id'])?(int)$m['_thumbnail_id']:null,
   'gallery_attachment_ids'=>$galleryIds,'categories'=>$categories[$id]??[],
   'source_created_at'=>$p['post_date'],'content_length'=>strlen((string)$p['post_content']),
   'excerpt_length'=>strlen((string)$p['post_excerpt'])
  ];
 }
 if (in_array('--json',$argv,true)) echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
 else {
  echo "EKHAYA — READ-ONLY CATALOGUE PREVIEW\n";
  echo "Published products: ".count($products)."\n";
  foreach ($report['products'] as $p) printf("#%d | %s | SKU: %s | Price: %s | Categories: %s | Thumbnail: %s\n",$p['source_product_id'],$p['name'],$p['sku']??'—',$p['regular_price']??'—',implode(', ',array_column($p['categories'],'name'))?:'—',$p['thumbnail_attachment_id']??'—');
  echo "\nNo database writes. No customer or order records accessed.\n";
 }
} catch (Throwable $e) { fwrite(STDERR,"Catalogue preview failed: ".$e->getMessage()."\n"); exit(1); }
