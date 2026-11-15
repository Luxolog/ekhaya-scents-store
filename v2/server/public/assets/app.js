const CART_KEY="ekhaya_store_cart_v1";
function readCart(){try{const rows=JSON.parse(localStorage.getItem(CART_KEY)||"[]");return new Map(rows.filter(x=>x&&x.id&&Number(x.qty)>0).map(x=>[String(x.id),{...x,id:String(x.id),qty:Math.min(99,Math.floor(Number(x.qty)))}]));}catch{return new Map();}}
const cart=readCart();
function saveCart(){try{localStorage.setItem(CART_KEY,JSON.stringify([...cart.values()]));}catch{}}
const money = n => new Intl.NumberFormat("en-ZA",{style:"currency",currency:"ZAR"}).format(Number(n||0));
const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
let catalogue=[];
let categories=[];
let activeCategory="all";
let searchTerm="";
let sortMode="featured";
function productPrice(p){return p.sale_price!==null&&p.sale_price!==""?Number(p.sale_price):Number(p.regular_price||0)}
function card(p){
 const image=p.image_path?'<img loading="lazy" src="'+esc(p.image_path)+'" alt="'+esc(p.name)+'">':'<div class="mini-bottle">'+esc(p.name)+'</div>';
 const available=p.stock_status==="in_stock";
 return '<article class="product-card"><a class="product-visual" href="product.html?slug='+encodeURIComponent(p.slug)+'">'+image+'</a><div class="product-info"><div><h3><a href="product.html?slug='+encodeURIComponent(p.slug)+'">'+esc(p.name)+'</a></h3><p>'+esc(p.short_description||"")+'</p><button class="add-button" data-add="'+esc(p.id)+'" '+(available?'':'disabled')+'>'+(available?'Add to bag +':'Currently unavailable')+'</button></div><strong class="product-price">'+money(productPrice(p))+'</strong></div></article>';
}
function emptyMessage(){return '<p class="catalogue-empty">No products match your selection. Try another category or search.</p>'}
function hasCategory(p,pattern){return p.categories?.some(c=>pattern.test((c.slug||"")+" "+(c.name||"")));}
function sortProducts(rows){
 const copy=[...rows];
 if(sortMode==="name-asc")copy.sort((a,b)=>a.name.localeCompare(b.name));
 if(sortMode==="price-asc")copy.sort((a,b)=>productPrice(a)-productPrice(b));
 if(sortMode==="price-desc")copy.sort((a,b)=>productPrice(b)-productPrice(a));
 return copy;
}
function renderProducts(){
 const query=searchTerm.toLocaleLowerCase();
 const searched=catalogue.filter(p=>!query||[p.name,p.sku,p.short_description,...(p.categories||[]).flatMap(c=>[c.name,c.slug])].some(v=>String(v||"").toLocaleLowerCase().includes(query)));
 const supplies=searched.filter(p=>hasCategory(p,/oil|bottle|atomiser|atomizer|packaging|box|base|accessor|starter|kit|suppl/i));
 const fragrance=searched.filter(p=>!supplies.includes(p)&&hasCategory(p,/origin|fragrance|perfume|him|her|men|women/i));
 const home=searched.filter(p=>hasCategory(p,/home|car|diffuser|linen|air-freshener/i));
 const show=(id,rows)=>{const el=document.getElementById(id);if(el)el.innerHTML=rows.length?sortProducts(rows).map(card).join(""):emptyMessage();};
 const selected=activeCategory==="all"?fragrance:fragrance.filter(p=>p.categories?.some(c=>c.slug===activeCategory));
 const selectedByGender=selected.filter(p=>{const f=document.querySelector(".filter.active")?.dataset.filter||"all";return f==="all"||(f==="him"?hasCategory(p,/him|men/i):hasCategory(p,/her|women/i));});
 show("fragranceGrid",selectedByGender);
 show("homeGrid",home);
 show("supplyGrid",supplies);
 const categoryWrap=document.getElementById("dynamicCategoryFilters");
 if(categoryWrap){
  const relevant=categories.filter(c=>fragrance.some(p=>p.categories?.some(pc=>pc.slug===c.slug)));
  categoryWrap.innerHTML='<button class="filter '+(activeCategory==="all"?"active":"")+'" data-category="all">All fragrances</button>'+relevant.map(c=>'<button class="filter '+(activeCategory===c.slug?"active":"")+'" data-category="'+esc(c.slug)+'">'+esc(c.name)+'</button>').join("");
 }
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
document.addEventListener("click",e=>{const b=e.target.closest("[data-category]");if(b){activeCategory=b.dataset.category;renderProducts();}const gender=e.target.closest("[data-filter]");if(gender){document.querySelectorAll("[data-filter]").forEach(x=>x.classList.remove("active"));gender.classList.add("active");renderProducts();}});\nconst searchInput=document.getElementById("catalogueSearch");if(searchInput)searchInput.addEventListener("input",()=>{searchTerm=searchInput.value.trim();renderProducts();});\nconst sortInput=document.getElementById("catalogueSort");if(sortInput)sortInput.addEventListener("change",()=>{sortMode=sortInput.value;renderProducts();});
const drawer=document.getElementById("cartDrawer"),scrim=document.getElementById("scrim");
function openCart(){drawer.classList.add("open");drawer.setAttribute("aria-hidden","false");scrim.classList.add("show");}
function closeCart(){drawer.classList.remove("open");drawer.setAttribute("aria-hidden","true");scrim.classList.remove("show");}
document.getElementById("cartTrigger").addEventListener("click",openCart);document.getElementById("cartClose").addEventListener("click",closeCart);scrim.addEventListener("click",closeCart);
document.getElementById("menuToggle").addEventListener("click",()=>{const nav=document.getElementById("mainNav");const open=nav.classList.toggle("open");document.getElementById("menuToggle").setAttribute("aria-expanded",String(open));});
document.querySelectorAll("#mainNav a").forEach(a=>a.addEventListener("click",()=>document.getElementById("mainNav").classList.remove("open")));
document.getElementById("year").textContent=new Date().getFullYear();
renderCart();
loadCatalogue().catch(()=>{document.getElementById("fragranceGrid").innerHTML=emptyMessage();document.getElementById("homeGrid").innerHTML=emptyMessage();document.getElementById("supplyGrid").innerHTML=emptyMessage();});
