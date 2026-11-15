const CART_KEY="ekhaya_store_cart_v1";
function readCart(){try{const rows=JSON.parse(localStorage.getItem(CART_KEY)||"[]");return new Map(rows.filter(x=>x&&x.id&&Number(x.qty)>0).map(x=>[String(x.id),{...x,id:String(x.id),qty:Math.min(99,Math.floor(Number(x.qty)))}]));}catch{return new Map();}}
const cart=readCart();
function saveCart(){try{localStorage.setItem(CART_KEY,JSON.stringify([...cart.values()]));}catch{}}
const money = n => new Intl.NumberFormat("en-ZA",{style:"currency",currency:"ZAR"}).format(Number(n||0));
const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
let catalogue=[];
let categories=[];
function productPrice(p){return p.sale_price!==null && p.sale_price!=="" ? Number(p.sale_price) : Number(p.regular_price||0)}
function card(p){
 const image=p.image_path ? '<img loading="lazy" src="'+esc(p.image_path)+'" alt="'+esc(p.name)+'">' : '<div class="mini-bottle">'+esc(p.name)+'</div>';
 return '<article class="product-card"><a class="product-visual" href="product.html?slug='+encodeURIComponent(p.slug)+'">'+image+'</a><div class="product-info"><div><h3><a href="product.html?slug='+encodeURIComponent(p.slug)+'">'+esc(p.name)+'</a></h3><p>'+esc(p.short_description||"")+'</p><button class="add-button" data-add="'+esc(p.id)+'">Add to bag +</button></div><strong class="product-price">'+money(productPrice(p))+'</strong></div></article>';
}
function emptyMessage(){return '<p class="catalogue-empty">New arrivals are being prepared. Please check back shortly.</p>'}
function renderProducts(){
 const filter=document.querySelector(".filter.active")?.dataset.filter||"all";
 const signature=catalogue.filter(p=>p.categories?.some(c=>/origin|fragrance|perfume|him|her/i.test(c.slug+" "+c.name)));
 const home=catalogue.filter(p=>p.categories?.some(c=>/home|car|diffuser|linen/i.test(c.slug+" "+c.name)));
 const source=signature.length?signature:catalogue;
 const shown=filter==="all"?source:source.filter(p=>p.categories?.some(c=>filter==="him"?/him|men/i.test(c.slug+" "+c.name):/her|women/i.test(c.slug+" "+c.name)));
 document.getElementById("fragranceGrid").innerHTML=shown.length?shown.map(card).join(""):emptyMessage();
 document.getElementById("homeGrid").innerHTML=home.length?home.map(card).join(""):emptyMessage();
}
async function loadCatalogue(){
 const [p,c]=await Promise.all([fetch("api/products?limit=100",{headers:{Accept:"application/json"}}),fetch("api/categories",{headers:{Accept:"application/json"}})]);
 if(!p.ok||!c.ok)throw new Error("Catalogue is temporarily unavailable");
 const pd=await p.json(),cd=await c.json();
 categories=cd.categories||[];
 catalogue=(pd.products||[]).map(x=>({...x,categories:x.categories||[]}));
 renderProducts();
}
function renderCart(){
 const items=[...cart.values()];
 document.getElementById("cartCount").textContent=items.reduce((s,p)=>s+p.qty,0);
 document.getElementById("cartSubtotal").textContent=money(items.reduce((s,p)=>s+p.price*p.qty,0));
 document.getElementById("cartItems").innerHTML=items.length?items.map(p=>'<div class="cart-line"><span>'+esc(p.name)+' × '+p.qty+'</span><span>'+money(p.price*p.qty)+' <button data-remove="'+esc(p.id)+'" aria-label="Remove '+esc(p.name)+'">×</button></span></div>').join(""):'<p class="empty-cart">Your bag is waiting for something special.</p>';
}
document.addEventListener("click",e=>{
 const add=e.target.closest("[data-add]");
 if(add){const p=catalogue.find(x=>String(x.id)===add.dataset.add);if(p){if(p.stock_status!=="in_stock")return;const current=cart.get(String(p.id))||{id:String(p.id),name:p.name,price:productPrice(p),qty:0};current.qty=Math.min(99,current.qty+1);cart.set(String(p.id),current);saveCart();renderCart();openCart();}}
 const remove=e.target.closest("[data-remove]");if(remove){cart.delete(remove.dataset.remove);saveCart();renderCart();}
});
document.querySelectorAll(".filter").forEach(btn=>btn.addEventListener("click",()=>{document.querySelectorAll(".filter").forEach(b=>b.classList.remove("active"));btn.classList.add("active");renderProducts();}));
const drawer=document.getElementById("cartDrawer"),scrim=document.getElementById("scrim");
function openCart(){drawer.classList.add("open");drawer.setAttribute("aria-hidden","false");scrim.classList.add("show");}
function closeCart(){drawer.classList.remove("open");drawer.setAttribute("aria-hidden","true");scrim.classList.remove("show");}
document.getElementById("cartTrigger").addEventListener("click",openCart);document.getElementById("cartClose").addEventListener("click",closeCart);scrim.addEventListener("click",closeCart);
document.getElementById("menuToggle").addEventListener("click",()=>{const nav=document.getElementById("mainNav");const open=nav.classList.toggle("open");document.getElementById("menuToggle").setAttribute("aria-expanded",String(open));});
document.querySelectorAll("#mainNav a").forEach(a=>a.addEventListener("click",()=>document.getElementById("mainNav").classList.remove("open")));
document.getElementById("year").textContent=new Date().getFullYear();
renderCart();
loadCatalogue().catch(()=>{document.getElementById("fragranceGrid").innerHTML=emptyMessage();document.getElementById("homeGrid").innerHTML=emptyMessage();});
