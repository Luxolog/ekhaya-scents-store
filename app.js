const products=[
["isibindi","ISIBINDI","The Scent of Courage","him",285,"assets/Isibindi%201.png"],
["umoya","UMOYA","The Scent of Spirit","him",285,"assets/Umoya%201.png"],
["amandla","AMANDLA","The Scent of Strength","him",285,"assets/Amandla%201.png"],
["ulonwabo","ULONWABO","The Scent of Happiness","her",285,"assets/Ubunono%201.png"],
["ubulumko","UBULUMKO","The Scent of Wisdom","her",285,"assets/Ubulumko%201.png"],
["ubunono","UBUNONO","The Scent of Elegance","her",285,"assets/Ubukhosi%201.png"],
["reed1","Reed Diffuser","150ml Home Fragrance","home",299,"assets/Reed%20Diffuser.jpeg"],
["reed2","Reed Diffuser","150ml Home Fragrance","home",299,"assets/Reed%20Diffuser.jpeg"],
["white-luxury","White Luxury","Car Perfume","car",65,"assets/Car%20Perfume.jpeg"],
["eucalyptus","Eucalyptus","Car Perfume","car",65,"assets/Car%20Perfume.jpeg"]
];

let cart=JSON.parse(localStorage.getItem("ekhaya_cart")||"[]");let activeFilter="all";let query="";
const money=n=>"R"+n.toFixed(2);
const $=id=>document.getElementById(id);

function filtered(){return products.filter(p=>{const cat=activeFilter==="all"||p[3]===activeFilter;const q=query.trim().toLowerCase();return cat&&(!q||p[1].toLowerCase().includes(q)||p[2].toLowerCase().includes(q)||p[3].toLowerCase().includes(q))})}
function render(list=filtered()){
  $("products").innerHTML=list.map(p=>`
    <article class="product-card">
      <div class="product-visual">
        <span class="tag">${p[3]}</span>
        <img src="${p[5]}" alt="${p[1]} — ${p[2]}" loading="lazy">
        <button class="btn add" onclick="add('${p[0]}')">Add to cart</button>
      </div>
      <div class="info"><h3>${p[1]}</h3><p>${p[2]}</p><b>${money(p[4])}</b></div>
    </article>`).join("");
}

function save(){localStorage.setItem("ekhaya_cart",JSON.stringify(cart));renderCart()}
function add(id){const x=cart.find(i=>i.id===id);x?x.qty++:cart.push({id,qty:1});save();openCart()}
function renderCart(){
  $("cartItems").innerHTML=cart.length?cart.map(x=>{
    const p=products.find(y=>y[0]===x.id);
    return `<div class="line"><img class="thumb-image" src="${p[5]}" alt="${p[1]}"><div><h4>${p[1]}</h4><p>${money(p[4])}</p><div class="qty"><button onclick="qty('${x.id}',-1)">−</button> ${x.qty} <button onclick="qty('${x.id}',1)">+</button></div></div><b>${money(p[4]*x.qty)}</b></div>`
  }).join(""):'<p style="padding:30px;text-align:center;color:#777">Your cart is empty.</p>';
  const total=cart.reduce((s,x)=>s+products.find(p=>p[0]===x.id)[4]*x.qty,0);
  $("subtotal").textContent=money(total);
  $("cartCount").textContent=cart.reduce((s,x)=>s+x.qty,0);
}
function qty(id,d){
  const x=cart.find(i=>i.id===id);
  x.qty+=d;
  if(x.qty<1)cart=cart.filter(i=>i.id!==id);
  save();
}
function openCart(){$("cart").classList.add("open");$("overlay").classList.add("open")}
function closeCart(){$("cart").classList.remove("open");$("overlay").classList.remove("open")}

document.querySelectorAll(".pills button").forEach(b=>b.onclick=()=>{
  document.querySelectorAll(".pills button").forEach(x=>x.classList.remove("active"));
  b.classList.add("active");
  render(b.dataset.filter==="all"?products:products.filter(p=>p[3]===b.dataset.filter));
});
$("cartBtn").onclick=openCart;
$("closeCart").onclick=closeCart;
$("overlay").onclick=closeCart;
$("checkout").onclick=()=>alert("Secure online checkout will be connected next. WhatsApp ordering is available during testing.");
$("whatsapp").onclick=()=>{
  if(!cart.length)return alert("Your cart is empty.");
  let text="Hello Ekhaya Scents, I would like to order:%0A"+cart.map(x=>{
    let p=products.find(y=>y[0]===x.id);
    return x.qty+" x "+p[1]+" — "+money(p[4]*x.qty);
  }).join("%0A");
  window.open("https://wa.me/27738468238?text="+encodeURIComponent(text),"_blank");
};
render();
renderCart();