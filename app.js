const products=[
["isibindi","ISIBINDI","The Scent of Courage","him",285,249,"assets/Isibindi%201.png"],
["umoya","UMOYA","The Scent of Spirit","him",285,249,"assets/Umoya%201.png"],
["amandla","AMANDLA","The Scent of Strength","him",285,249,"assets/Amandla%201.png"],
["ulonwabo","ULONWABO","The Scent of Happiness","her",285,249,"assets/Ubunono%201.png"],
["ubulumko","UBULUMKO","The Scent of Wisdom","her",285,249,"assets/Ubulumko%201.png"],
["ubunono","UBUNONO","The Scent of Elegance","her",285,249,"assets/Ubukhosi%201.png"],
["reed1","Reed Diffuser","150ml Home Fragrance","home",349,299,"assets/Reed%20Diffuser.jpeg"],
["reed2","Reed Diffuser","150ml Home Fragrance","home",349,299,"assets/Reed%20Diffuser.jpeg"],
["car-perfume","Scented Car Perfume","7ml Fragrance for Your Car","car",65,50,"assets/Car%20Perfume.jpeg"]
];

const scentDetails={
  isibindi:{name:"ISIBINDI",meaning:"The Scent of Courage",description:"A confident, refined fragrance with a fresh opening, a warm aromatic heart and a smooth woody finish.",top:"Pineapple, bergamot, blackcurrant",heart:"Birch, jasmine, rose",base:"Musk, oakmoss, amber, vanilla"},
  umoya:{name:"UMOYA",meaning:"The Scent of Spirit",description:"Fresh, powerful and unmistakably masculine, balancing bright citrus with aromatic spice and a deep amber-woody dry-down.",top:"Bergamot, lemon, pepper",heart:"Lavender, geranium, patchouli",base:"Ambroxan, cedar, vetiver"},
  amandla:{name:"AMANDLA",meaning:"The Scent of Strength",description:"An energetic aquatic-woody profile that opens fresh and vibrant before settling into a warm, confident base.",top:"Grapefruit, mandarin, marine accords",heart:"Bay leaf, jasmine",base:"Guaiac wood, patchouli, ambergris"},
  ulonwabo:{name:"ULONWABO",meaning:"The Scent of Happiness",description:"A luminous gourmand floral built around soft florals, sweet praline and a warm vanilla-patchouli finish.",top:"Pear, blackcurrant",heart:"Iris, jasmine, orange blossom",base:"Praline, vanilla, patchouli, tonka bean"},
  ubulumko:{name:"UBULUMKO",meaning:"The Scent of Wisdom",description:"A seductive, sophisticated fragrance where rich coffee and white florals meet a smooth, sweet vanilla base.",top:"Pink pepper, orange blossom",heart:"Coffee, jasmine, bitter almond",base:"Vanilla, patchouli, cedar"},
  ubunono:{name:"UBUNONO",meaning:"The Scent of Elegance",description:"A polished floral-gourmand composition with creamy almond, white florals and a warm tonka-cacao finish.",top:"Almond, coffee, bergamot",heart:"Tuberose, jasmine, orange blossom",base:"Tonka bean, cacao, vanilla, sandalwood"}
};

let cart=JSON.parse(localStorage.getItem("ekhaya_cart")||"[]");let activeFilter="all";let query="";
const reseller=JSON.parse(localStorage.getItem("ekhaya_reseller")||"null");
const isReseller=!!(reseller&&reseller.tag==="RESELLER");
function resellerPrice(p,qty){if(!isReseller)return p[5];if(p[3]==="him"||p[3]==="her")return qty>=16?130:qty>=5?135:p[5];if(p[3]==="home")return qty>=5?190:p[5];if(p[3]==="car")return qty>=25?25:qty>=15?30:qty>=10?35:p[5];return p[5]}
function resellerTierText(p){if(p[3]==="him"||p[3]==="her")return "5–15 R135 | 16–20 R130";if(p[3]==="home")return "5+ R190";if(p[3]==="car")return "10 R35 | 15 R30 | 25 R25";return ""}
const money=n=>"R"+n.toFixed(2);
const $=id=>document.getElementById(id);

function isMatch(p){const cat=activeFilter==="all"||(activeFilter==="origin"&&(p[3]==="him"||p[3]==="her"))||p[3]===activeFilter;const q=query.trim().toLowerCase();return cat&&(!q||p[1].toLowerCase().includes(q)||p[2].toLowerCase().includes(q)||p[3].toLowerCase().includes(q))}
function filtered(){return products.filter(isMatch)}
function categoryLabel(c){return c==="him"?"For Him":c==="her"?"For Her":c==="home"?"Home":"Car"}
function render(list=filtered()){
  $("products").innerHTML=list.map(p=>`
    <article class="product-card" onclick="add('${p[0]}')">
      <div class="product-visual">
        <span class="tag">${categoryLabel(p[3])}</span>
        ${p[4]>p[5]?'<span class="sale-tag">SALE</span>':""}
        <img src="${p[6]}" alt="${p[1]} — ${p[2]}" loading="lazy">
        <button class="btn add" onclick="event.stopPropagation();add('${p[0]}')">Add to cart</button>
      </div>
      <div class="info"><h3>${p[1]}</h3><p>${p[2]}</p><div class="price">${p[4]>p[5]?'<del>'+money(p[4])+'</del>':""}<b>${money(p[5])}</b>${p[4]>p[5]?'<small>SAVE '+money(p[4]-p[5])+'</small>':""}</div></div>
    </article>`).join("");
}

function save(){localStorage.setItem("ekhaya_cart",JSON.stringify(cart));renderCart()}
function add(id){const x=cart.find(i=>i.id===id);x?x.qty++:cart.push({id,qty:1});save();openCart()}
function selectedShipping(){const el=document.querySelector('input[name="shipping"]:checked');return el?Number(el.value):75}
function cartPricing(){
  if(isReseller){let subtotal=0;cart.forEach(x=>{const p=products.find(y=>y[0]===x.id);if(p)subtotal+=resellerPrice(p,x.qty)*x.qty});return {subtotal,duoCount:0,singleCount:0,originQty:0}}
  let originQty=0,subtotal=0;
  cart.forEach(x=>{const p=products.find(y=>y[0]===x.id);if(!p)return;if(p[3]==="him"||p[3]==="her")originQty+=x.qty;else subtotal+=p[5]*x.qty});
  const duoCount=Math.floor(originQty/2),singleCount=originQty%2;
  subtotal+=duoCount*430+singleCount*249;
  return {subtotal,duoCount,singleCount,originQty};
}
function renderCart(){
  $("cartItems").innerHTML=cart.length?cart.map(x=>{
    const p=products.find(y=>y[0]===x.id);const unit=resellerPrice(p,x.qty);
    return `<div class="line"><img class="thumb-image" src="${p[6]}" alt="${p[1]}"><div><h4>${p[1]}</h4><p>${isReseller?"<strong>RESELLER PRICE</strong>":"<del>"+money(p[4])+"</del> <strong>"+money(p[5])+"</strong>"}</p><div class="qty"><button onclick="qty('${x.id}',-1)">−</button> ${x.qty} <button onclick="qty('${x.id}',1)">+</button></div></div><b>${money(p[5]*x.qty)}</b></div>`
  }).join(""):'<p style="padding:30px;text-align:center;color:#777">Your cart is empty.</p>';
  const pricing=cartPricing(),shipping=selectedShipping(),total=pricing.subtotal+shipping;
  $("subtotal").textContent=money(pricing.subtotal);
  $("shippingTotal").textContent=money(shipping);
  $("cartTotal").textContent=money(total);
  $("cartCount").textContent=cart.reduce((s,x)=>s+x.qty,0);
  $("cartPromo").innerHTML=isReseller?'<strong>RESELLER ACCOUNT ACTIVE</strong> — reseller pricing is applied automatically.':pricing.duoCount?'<strong>ORIGIN DUO:</strong> '+pricing.duoCount+' × 2 fragrances at R430 each — promotion applied.':"";
}
function qty(id,d){
  const x=cart.find(i=>i.id===id);
  x.qty+=d;
  if(x.qty<1)cart=cart.filter(i=>i.id!==id);
  save();
}
function openCart(){$("cart").classList.add("open");$("overlay").classList.add("open")}
function closeCart(){$("cart").classList.remove("open");$("overlay").classList.remove("open")}

function setFilter(filter){
  activeFilter=filter;
  document.querySelectorAll(".pills button").forEach(x=>x.classList.toggle("active",x.dataset.filter===filter));
  render();
}
document.querySelectorAll(".pills button").forEach(b=>b.onclick=()=>setFilter(b.dataset.filter));

document.querySelectorAll("[data-category]").forEach(link=>link.onclick=()=>{
  setFilter(link.dataset.category);
});

const shopSearch=$("shopSearch");
const searchInput=$("searchInput");
const searchPanel=$("searchPanel");
const searchToggle=$("searchToggle");
const clearSearch=$("clearSearch");

function setQuery(value){
  query=value;
  if(shopSearch)shopSearch.value=value;
  if(searchInput)searchInput.value=value;
  render();
}
if(shopSearch)shopSearch.addEventListener("input",e=>setQuery(e.target.value));
if(searchInput)searchInput.addEventListener("input",e=>setQuery(e.target.value));
if(clearSearch)clearSearch.onclick=()=>setQuery("");
if(searchToggle)searchToggle.onclick=()=>{
  searchPanel.classList.toggle("open");
  if(searchPanel.classList.contains("open"))searchInput.focus();
};

document.querySelectorAll(".category-strip a").forEach(link=>link.addEventListener("click",()=>{
  setFilter(link.dataset.category);
}));
$("cartBtn").onclick=openCart;
$("closeCart").onclick=closeCart;
$("overlay").onclick=closeCart;
document.querySelectorAll('input[name="shipping"]').forEach(r=>r.addEventListener("change",renderCart));
$("checkout").onclick=()=>alert("Secure online checkout will be connected next. Your selected delivery option will be included.");
document.querySelectorAll(".scent-name").forEach(btn=>btn.onclick=()=>openScent(btn.dataset.scent));
document.querySelectorAll("[data-close-scent]").forEach(el=>el.onclick=closeScent);
document.addEventListener("keydown",e=>{if(e.key==="Escape")closeScent()});
function openScent(id){
  const s=scentDetails[id]; if(!s)return;
  $("scentModalTitle").textContent=s.name;
  $("scentModalMeaning").textContent=s.meaning;
  $("scentModalDescription").textContent=s.description;
  $("scentTop").textContent=s.top;
  $("scentHeart").textContent=s.heart;
  $("scentBase").textContent=s.base;
  $("scentShop").onclick=()=>{closeScent();setFilter(id==="isibindi"||id==="umoya"||id==="amandla"?"him":"her")};
  $("scentModal").classList.add("open");
  $("scentModal").setAttribute("aria-hidden","false");
}
function closeScent(){$("scentModal").classList.remove("open");$("scentModal").setAttribute("aria-hidden","true")}

$("whatsapp").onclick=()=>{
  if(!cart.length)return alert("Your cart is empty.");
  const pricing=cartPricing(),shipping=selectedShipping();
  let text="Hello Ekhaya Scents, I would like to order:%0A"+cart.map(x=>{
    let p=products.find(y=>y[0]===x.id);
    return x.qty+" x "+p[1]+" — "+money(resellerPrice(p,x.qty)*x.qty);
  }).join("%0A")+"%0A%0ASubtotal: "+money(pricing.subtotal)+"%0ADelivery: "+(shipping===75?"Locker to Locker":"Store to Door")+" — "+money(shipping)+"%0ATotal: "+money(pricing.subtotal+shipping);
  window.open("https://wa.me/27738468238?text="+encodeURIComponent(text),"_blank");
};
render();
renderCart();