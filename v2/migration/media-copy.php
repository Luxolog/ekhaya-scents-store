<?php
declare(strict_types=1);

/**
 * Copies only media referenced by the migrated product_images table.
 * Default is dry-run; pass --commit to copy files.
 * Config:
 *   media.source_uploads_dir = absolute extracted WordPress uploads directory
 *   media.target_public_dir = absolute v2/server/public directory
 */
$configPath=__DIR__.'/config.local.php';
if(!is_file($configPath)){fwrite(STDERR,"Missing migration/config.local.php.\n");exit(2);}
$config=require $configPath;$commit=in_array('--commit',$argv,true);
try{
 $c=$config['target'];$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$c['host'],$c['port']??'3306',$c['database']);
 $db=new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $sourceRoot=realpath((string)($config['media']['source_uploads_dir']??''));$targetRoot=(string)($config['media']['target_public_dir']??'');
 if(!$sourceRoot||!is_dir($sourceRoot)||$targetRoot==='')throw new RuntimeException('Configure valid media.source_uploads_dir and media.target_public_dir paths.');
 $q=$db->query('SELECT DISTINCT source_path FROM product_images ORDER BY source_path');$paths=$q->fetchAll(PDO::FETCH_COLUMN);
 $counts=['referenced'=>count($paths),'copied'=>0,'already_present'=>0,'missing_source'=>0,'unsafe'=>0,'errors'=>0];
 echo 'EKHAYA PRODUCT MEDIA COPY | '.($commit?'COMMIT':'DRY RUN')."\n";
 foreach($paths as $stored){
  $stored=(string)$stored;
  if(!str_starts_with($stored,'uploads/')){$counts['unsafe']++;continue;}
  $relative=substr($stored,8);
  if($relative===''||str_contains($relative,'..')||str_contains($relative,"\0")){$counts['unsafe']++;continue;}
  $source=$sourceRoot.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
  $real=realpath($source);
  if(!$real||!str_starts_with($real,$sourceRoot.DIRECTORY_SEPARATOR)||!is_file($real)){$counts['missing_source']++;continue;}
  $destination=rtrim($targetRoot,'/\\').'/uploads/'.$relative;
  if(is_file($destination)){
   if(hash_file('sha256',$real)===hash_file('sha256',$destination)){$counts['already_present']++;continue;}
   $counts['errors']++;fwrite(STDERR,"Existing destination differs; refusing overwrite: {$stored}\n");continue;
  }
  if(!$commit){$counts['copied']++;continue;}
  $directory=dirname($destination);
  if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory)){$counts['errors']++;continue;}
  if(!copy($real,$destination)){$counts['errors']++;continue;}
  $counts['copied']++;
 }
 foreach($counts as $k=>$v)echo ucfirst(str_replace('_',' ',$k)).": {$v}\n";
 echo $commit?"Copy pass finished. Review missing files and errors before enabling product imagery.\n":"Dry run only. No files copied. Re-run with --commit to copy referenced media.\n";
 if($counts['errors']>0||$counts['unsafe']>0)exit(1);
}catch(Throwable $e){fwrite(STDERR,'Media copy stopped: '.$e->getMessage()."\n");exit(1);}
