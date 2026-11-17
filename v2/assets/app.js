const asset = name => "assets/catalogue/" + encodeURIComponent(name);
const sizePrices = {
  origin: {"30ml":169,"50ml":249,"100ml":349},
  inspired: {"30ml":199,"50ml":299,"100ml":399}
};
const signatureProducts = [
 {id:"umoya",name:"UMOYA",subtitle:"The Scent of Spirit",audience:"him",kind:"origin",image:asset("Origin_Umoya_For Him.png")},
 {id:"amandla",name:"AMANDLA",subtitle:"The Scent of Strength",audience:"him",kind:"origin",image:asset("Amandla_ Scent of Strength_For Him.png")},
 {id:"isibindi",name:"ISIBINDI",subtitle:"The Scent of Courage",audience:"him",kind:"origin",image:asset("Origin Isibindi Luxury Fragrance_For Him.png")},
 {id:"isithunzi",name:"ISITHUNZI",subtitle:"The Scent of Presence",audience:"him",kind:"origin",image:asset("Origin Isithunzi_For Him.png")},
 {id:"ulonwabo",name:"ULONWABO",subtitle:"The Scent of Happiness",audience:"her",kind:"origin",image:asset("Origin_Ulonwabo_For Her.png")},
 {id:"ubulumko",name:"UBULUMKO",subtitle:"The Scent of Wisdom",audience:"her",kind:"origin",image:asset("Origin_ Ubulumko_For Her.png")},
 {id:"ubunono",name:"UBUNONO",subtitle:"The Scent of Elegance",audience:"her",kind:"origin",image:asset("Origin_Ubunono_For Her.png")},
 {id:"ubukhosi",name:"UBUKHOSI",subtitle:"The Scent of Royalty",audience:"her",kind:"origin",image:asset("Origin_Ubukhosi_For Her.png")}
];
const inspiredProducts = [
 {id:"lady-million",name:"LADY MILLION",subtitle:"Origin Inspired · For Her",audience:"her",kind:"inspired",image:asset("Origin Inpired Lady Million_For Her.png")},
 {id:"black-opium",name:"BLACK OPIUM",subtitle:"Origin Inspired · For Her",audience:"her",kind:"inspired",image:asset("Origin Inspired Black Opium_For Her.png")},
 {id:"good-girl",name:"GOOD GIRL",subtitle:"Origin Inspired · For Her",audience:"her",kind:"inspired",image:asset("Origin Inspired Good Girl_For Her.png")},
 {id:"invictus",name:"INVICTUS",subtitle:"Origin Inspired · For Him",audience:"him",kind:"inspired",image:asset("Origin Inspired Invictus_For Him.png")},
 {id:"la-vie-est-belle",name:"LA VIE EST BELLE",subtitle:"Origin Inspired · For Her",audience:"her",kind:"inspired",image:asset("Origin Inspired_ La Vie Est Belle_For Her.png")}
];
const homeProducts = [
 {id:"reed-diffuser",name:"Reed Diffuser",subtitle:"150ml · Home fragrance",price:299,image:asset("Reed Diffuser.jpeg")},
 {id:"linen-spray",name:"Linen Spray",subtitle:"250ml · Home fragrance",price:149,image:asset("Linen Spray.png")},
 {id:"car-perfume",name:"Car Perfume",subtitle:"Every journey, beautifully scented",price:65,image:asset("7ml Car Perfume Amber and Oud.png")}
];

const supplyProducts = [
 {id:"luxury-packaging",name:"Luxury Packaging Collection",subtitle:"Bottles and presentation options",category:"packaging",image:asset("Ekhaya Scents Luxury Packaging Collection.png")},
 {id:"black-perfume-box",name:"Black Perfume Box",subtitle:"Luxury fragrance presentation",category:"packaging",image:asset("Elegant Black Perfume Box Showcase.png")},
 {id:"white-luxury-box",name:"White Luxury Box",subtitle:"Fragrance packaging",category:"packaging",image:asset("Ekhaya Scents White Luxury Box.png")},
 {id:"nova-black-box",name:"Nova Black Scent Box",subtitle:"30ml presentation box",category:"packaging",image:asset("Elegant Nova 30ml Black Scent Box.png")},
 {id:"fragrance-base",name:"Ekhaya Fragrance Base",subtitle:"Fragrance-making essential",category:"tools",image:asset("Ekhaya Fragrance Base Product Label.png")},
 {id:"fragrance-base-2l",name:"Ekhaya Fragrance Base",subtitle:"2L product label",category:"tools",image:asset("Ekhaya Fragrance Base Product Label 2L.png")},
 {id:"dosing-syringe",name:"Fragrance Dosing Syringe",subtitle:"Production accessory",category:"tools",image:asset("Ekhaya Scents Fragrance Dosing Syringe.png")},
 {id:"hand-crimper",name:"Hand-held Crimper Tool",subtitle:"Perfume production tool",category:"tools",image:asset("Ekhaya Scents Hand-held Crimper Tool.png")},
 {id:"crimp",name:"Ekhaya Scents Crimp",subtitle:"Packaging accessory",category:"tools",image:asset("Ekhaya Scents Crimp.png")},
 {id:"reed-diffuser-luxury",name:"Luxury Reed Diffuser",subtitle:"Home fragrance",category:"home",image:asset("Ekhaya Scents Luxury Reed Diffuser.png")},
 {id:"reed-diffuser-emerald",name:"Emerald Reed Diffuser",subtitle:"Home fragrance",category:"home",image:asset("Ekhaya Scents Emerald Reed Diffuser.png")},
 {id:"linen-spray-supply",name:"Linen Spray",subtitle:"Home fragrance",category:"home",image:asset("Linen Spray.png")},
 {id:"gold-hanging-diffuser",name:"Gold Hanging Diffusers",subtitle:"Car fragrance accessory",category:"home",image:asset("Luxury Gold ES Hanging Diffusers.png")}
];
function supplyCard(p){
 const subject=encodeURIComponent("Ekhaya Scents catalogue enquiry: "+p.name);
 return `<article class="product-card supply-product-card"><div class="product-visual"><img class="product-photo" src="${p.image}" alt="${p.name}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="image-fallback" hidden>${p.name}</span></div><div class="product-info"><div><h3>${p.name}</h3><p>${p.subtitle}</p><a class="add-button supply-enquire" href="mailto:hello@ekhayascents.co.za?subject=${subject}">Enquire about this item ↗</a></div><strong class="product-price">Price on enquiry</strong></div></article>`;
}
function renderSupplyProducts(filter="all"){
 const grid=document.getElementById("supplyProductsGrid");
 if(grid) grid.innerHTML=supplyProducts.filter(p=>filter==="all"||p.category===filter).map(supplyCard).join("");
}

const cart = new Map();
const money = n => new Intl.NumberFormat("en-ZA",{style:"currency",currency:"ZAR",maximumFractionDigits:0}).format(n);
function card(p){
 const isPerfume=Boolean(p.kind), sizes=isPerfume?Object.keys(sizePrices[p.kind]):[];
 const price=isPerfume?sizePrices[p.kind]["30ml"]:p.price;
 return `<article class="product-card"><div class="product-visual"><img class="product-photo" src="${p.image}" alt="${p.name}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="image-fallback" hidden>${p.name}</span></div><div class="product-info"><div><h3>${p.name}</h3><p>${p.subtitle||""}</p>${isPerfume?`<label class="size-label" for="size-${p.id}">Choose size</label><select class="size-select" id="size-${p.id}" data-size-for="${p.id}">${sizes.map(s=>`<option value="${s}">${s} — ${money(sizePrices[p.kind][s])}</option>`).join("")}</select>`: ""}<button class="add-button" data-add="${p.id}">Add to bag +</button></div><strong class="product-price" data-price-for="${p.id}">${money(price)}</strong></div></article>`
}
function renderProducts(){
 const signatureFilter=document.querySelector('.filter.active[data-collection="signature"]')?.dataset.filter||"all";
 const inspiredFilter=document.querySelector('.filter.active[data-collection="inspired"]')?.dataset.filter||"all";
 document.getElementById("signatureGrid").innerHTML=signatureProducts.filter(p=>signatureFilter==="all"||p.audience===signatureFilter).map(card).join("");
 document.getElementById("inspiredGrid").innerHTML=inspiredProducts.filter(p=>inspiredFilter==="all"||p.audience===inspiredFilter).map(card).join("");
 document.getElementById("homeGrid").innerHTML=homeProducts.map(card).join("");
}
function renderCart(){
 const items=[...cart.values()];
 document.getElementById("cartCount").textContent=items.reduce((s,p)=>s+p.qty,0);
 document.getElementById("cartSubtotal").textContent=money(items.reduce((s,p)=>s+p.price*p.qty,0));
 document.getElementById("cartItems").innerHTML=items.length?items.map(p=>`<div class="cart-line"><span>${p.name}${p.size?" · "+p.size:""} × ${p.qty}</span><span>${money(p.price*p.qty)} <button data-remove="${p.key}" aria-label="Remove ${p.name}">×</button></span></div>`).join(""):'<p class="empty-cart">Your bag is waiting for something special.</p>';
}
document.addEventListener("change",e=>{
 const select=e.target.closest("[data-size-for]");if(!select)return;
 const product=[...signatureProducts,...inspiredProducts].find(p=>p.id===select.dataset.sizeFor);
 if(product){const price=sizePrices[product.kind][select.value];const display=document.querySelector(`[data-price-for="${product.id}"]`);if(display)display.textContent=money(price);}
});
document.addEventListener("click",e=>{
 const add=e.target.closest("[data-add]");
 if(add){
  const p=[...signatureProducts,...inspiredProducts,...homeProducts].find(x=>x.id===add.dataset.add);
  if(p){
   const selector=document.querySelector(`[data-size-for="${p.id}"]`);
   const size=selector?.value||null;
   const price=p.kind?sizePrices[p.kind][size]:p.price;
   const key=p.id+(size?"-"+size:"");
   const current=cart.get(key)||{...p,key,size,price,qty:0};
   current.qty++;cart.set(key,current);renderCart();openCart();
  }
 }
 const remove=e.target.closest("[data-remove]");if(remove){cart.delete(remove.dataset.remove);renderCart();}
});
document.querySelectorAll(".filter").forEach(btn=>btn.addEventListener("click",()=>{
 const collection=btn.dataset.collection;
 document.querySelectorAll('.filter[data-collection="'+collection+'"]').forEach(b=>b.classList.remove("active"));
 btn.classList.add("active");renderProducts();
}));
document.querySelectorAll("[data-supply-filter]").forEach(btn=>btn.addEventListener("click",()=>{document.querySelectorAll("[data-supply-filter]").forEach(b=>b.classList.remove("active"));btn.classList.add("active");renderSupplyProducts(btn.dataset.supplyFilter);}));
const drawer=document.getElementById("cartDrawer"),scrim=document.getElementById("scrim");
function openCart(){drawer.classList.add("open");drawer.setAttribute("aria-hidden","false");scrim.classList.add("show");}
function closeCart(){drawer.classList.remove("open");drawer.setAttribute("aria-hidden","true");scrim.classList.remove("show");}
document.getElementById("cartTrigger").addEventListener("click",openCart);document.getElementById("cartClose").addEventListener("click",closeCart);scrim.addEventListener("click",closeCart);
document.getElementById("menuToggle").addEventListener("click",()=>{const nav=document.getElementById("mainNav");const open=nav.classList.toggle("open");document.getElementById("menuToggle").setAttribute("aria-expanded",String(open));});
document.querySelectorAll("#mainNav a").forEach(a=>a.addEventListener("click",()=>document.getElementById("mainNav").classList.remove("open")));
document.getElementById("year").textContent=new Date().getFullYear();
renderProducts();renderSupplyProducts();renderCart();
