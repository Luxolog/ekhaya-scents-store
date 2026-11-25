<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__ . '/config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['site']['allowed_origins'], true)) { header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin'); }
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'POST required']); exit; }
if (!in_array($origin,$config['site']['allowed_origins'],true)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Origin not allowed']); exit; }
function failCheckout(string $message,int $status=422): never { http_response_code($status); echo json_encode(['ok'=>false,'error'=>$message]); exit; }
function pfSignature(array $data,string $passphrase): string {
 $parts=[]; foreach($data as $key=>$value) if($value!=='' && $value!==null) $parts[]=$key.'='.urlencode(trim((string)$value));
 $query=implode('&',$parts); if($passphrase!=='') $query.='&passphrase='.urlencode(trim($passphrase)); return md5($query);
}
$input=json_decode(file_get_contents('php://input'),true);
if(!is_array($input)) failCheckout('Invalid checkout request.');
$name=trim((string)($input['name']??'')); $email=filter_var($input['email']??'',FILTER_VALIDATE_EMAIL);
$ack=($input['privacyNoticeAcknowledged']??false)===true; $items=$input['items']??[]; $shipping=(int)($input['shipping']??0);
if($name===''||!$email||!$ack||!is_array($items)||count($items)<1||count($items)>30) failCheckout('Please provide your name, valid email, privacy acknowledgement and cart items.');
if(!in_array($shipping,[75,105],true)) failCheckout('Invalid delivery option.');
$catalogue=['isibindi'=>['name'=>'ISIBINDI','category'=>'origin','price'=>249],'umoya'=>['name'=>'UMOYA','category'=>'origin','price'=>249],'amandla'=>['name'=>'AMANDLA','category'=>'origin','price'=>249],'ulonwabo'=>['name'=>'ULONWABO','category'=>'origin','price'=>249],'ubulumko'=>['name'=>'UBULUMKO','category'=>'origin','price'=>249],'ubunono'=>['name'=>'UBUNONO','category'=>'origin','price'=>249],'reed1'=>['name'=>'Reed Diffuser','category'=>'home','price'=>299],'reed2'=>['name'=>'Reed Diffuser','category'=>'home','price'=>299],'car-perfume'=>['name'=>'Scented Car Perfume','category'=>'car','price'=>50]];
$originQty=0;$otherSubtotal=0;$normalized=[];
foreach($items as $item){$id=(string)($item['id']??'');$qty=filter_var($item['qty']??null,FILTER_VALIDATE_INT);if(!isset($catalogue[$id])||!$qty||$qty<1||$qty>100)failCheckout('One or more cart items are invalid.');$p=$catalogue[$id];if($p['category']==='origin')$originQty+=$qty;else$otherSubtotal+=$p['price']*$qty;$normalized[]=['id'=>$id,'name'=>$p['name'],'price'=>$p['price'],'qty'=>$qty];}
$subtotal=$otherSubtotal+intdiv($originQty,2)*430+($originQty%2)*249;$total=$subtotal+$shipping;
try{$pdo=new PDO('mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset=utf8mb4',$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);$orderNumber='ES-'.date('YmdHis').'-'.bin2hex(random_bytes(4));$pdo->beginTransaction();$stmt=$pdo->prepare("INSERT INTO orders (order_number,email,amount,shipping_amount,payment_status,fulfillment_status) VALUES (?,?,?,?,'pending','pending')");$stmt->execute([$orderNumber,$email,number_format($total,2,'.',''),number_format($shipping,2,'.','')]);$orderId=(int)$pdo->lastInsertId();$line=$pdo->prepare('INSERT INTO order_items (order_id,product_code,product_name,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)');foreach($normalized as $p)$line->execute([$orderId,$p['id'],$p['name'],$p['qty'],number_format($p['price'],2,'.',''),number_format($p['price']*$p['qty'],2,'.','')]);$pdo->commit();}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Ekhaya checkout DB error: '.$e->getMessage());failCheckout('We could not create your order. Please try again.',500);}
$pf=$config['payfast'];if(empty($pf['merchant_id'])||empty($pf['merchant_key'])||str_starts_with($pf['merchant_id'],'YOUR_'))failCheckout('PayFast Sandbox credentials have not been configured.',503);
$fields=['merchant_id'=>$pf['merchant_id'],'merchant_key'=>$pf['merchant_key'],'return_url'=>$pf['return_url'],'cancel_url'=>$pf['cancel_url'],'notify_url'=>$pf['notify_url'],'name_first'=>mb_substr($name,0,100),'email_address'=>$email,'m_payment_id'=>$orderNumber,'amount'=>number_format($total,2,'.',''),'item_name'=>'Ekhaya Scents order '.$orderNumber];$fields['signature']=pfSignature($fields,(string)$pf['passphrase']);echo json_encode(['ok'=>true,'endpoint'=>$pf['sandbox']?'https://sandbox.payfast.co.za/eng/process':'https://www.payfast.co.za/eng/process','fields'=>$fields]);
