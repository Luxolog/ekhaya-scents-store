const signatureProducts = [
 {id:"umoya",name:"UMOYA",subtitle:"The Scent of Spirit",price:285,audience:"him"},
 {id:"amandla",name:"AMANDLA",subtitle:"The Scent of Strength",price:285,audience:"him"},
 {id:"isibindi",name:"ISIBINDI",subtitle:"The Scent of Courage",price:285,audience:"him"},
 {id:"ulonwabo",name:"ULONWABO",subtitle:"The Scent of Happiness",price:285,audience:"her"},
 {id:"ubulumko",name:"UBULUMKO",subtitle:"The Scent of Wisdom",price:285,audience:"her"},
 {id:"ubunono",name:"UBUNONO",subtitle:"The Scent of Elegance",price:285,audience:"her"}
];
const homeProducts = [
 {id:"reed-diffuser",name:"Reed Diffuser",subtitle:"150ml · Home fragrance",price:299},
 {id:"linen-spray",name:"Linen Spray",subtitle:"250ml · Home fragrance",price:149},
 {id:"car-perfume",name:"Car Perfume",subtitle:"Every journey, beautifully scented",price:65}
];
const cart = new Map();
const money = n => new Intl.NumberFormat("en-ZA",{style:"currency",currency:"ZAR"}).format(n);
function card(p){return `<article class="product-card"><div class="product-visual"><div class="mini-bottle">${p.name.split(" ")[0]}</div></div><div class="product-info"><div><h3>${p.name}</h3><p>${p.subtitle||""}</p><button class="add-button" data-add="${p.id}">Add to bag +</button></div><strong class="product-price">${money(p.price)}</strong></div></article>`}
function renderProducts(){const filter=document.querySelector(".filter.active")?.dataset.filter||"all";document.getElementById("fragranceGrid").innerHTML=signatureProducts.filter(p=>filter==="all"||p.audience===filter).map(card).join("");document.getElementById("homeGrid").innerHTML=homeProducts.map(card).join("");}
function renderCart(){const items=[...cart.values()];document.getElementById("cartCount").textContent=items.reduce((s,p)=>s+p.qty,0);document.getElementById("cartSubtotal").textContent=money(items.reduce((s,p)=>s+p.price*p.qty,0));document.getElementById("cartItems").innerHTML=items.length?items.map(p=>`<div class="cart-line"><span>${p.name} × ${p.qty}</span><span>${money(p.price*p.qty)} <button data-remove="${p.id}" aria-label="Remove ${p.name}">×</button></span></div>`).join(""):'<p class="empty-cart">Your bag is waiting for something special.</p>';}
document.addEventListener("click",e=>{const add=e.target.closest("[data-add]");if(add){const p=[...signatureProducts,...homeProducts].find(x=>x.id===add.dataset.add);if(p){const current=cart.get(p.id)||{...p,qty:0};current.qty++;cart.set(p.id,current);renderCart();openCart();}}const remove=e.target.closest("[data-remove]");if(remove){cart.delete(remove.dataset.remove);renderCart();}});
document.querySelectorAll(".filter").forEach(btn=>btn.addEventListener("click",()=>{document.querySelectorAll(".filter").forEach(b=>b.classList.remove("active"));btn.classList.add("active");renderProducts();}));
const drawer=document.getElementById("cartDrawer"),scrim=document.getElementById("scrim");
function openCart(){drawer.classList.add("open");drawer.setAttribute("aria-hidden","false");scrim.classList.add("show");}
function closeCart(){drawer.classList.remove("open");drawer.setAttribute("aria-hidden","true");scrim.classList.remove("show");}
document.getElementById("cartTrigger").addEventListener("click",openCart);document.getElementById("cartClose").addEventListener("click",closeCart);scrim.addEventListener("click",closeCart);
document.getElementById("menuToggle").addEventListener("click",()=>{const nav=document.getElementById("mainNav");const open=nav.classList.toggle("open");document.getElementById("menuToggle").setAttribute("aria-expanded",String(open));});
document.querySelectorAll("#mainNav a").forEach(a=>a.addEventListener("click",()=>document.getElementById("mainNav").classList.remove("open")));
document.getElementById("year").textContent=new Date().getFullYear();
renderProducts();renderCart();