const money = n => new Intl.NumberFormat("en-ZA",{style:"currency",currency:"ZAR"}).format(Number(n||0));
const esc = value => String(value ?? "").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const catalogue = [
 {id:"umoya",name:"Umoya",short_description:"A fresh, confident signature for him.",group:"him",kind:"fragrance"},
 {id:"amandla",name:"Amandla",short_description:"An energetic expression of strength.",group:"him",kind:"fragrance"},
 {id:"isibindi",name:"Isibindi",short_description:"A bold signature with depth and character.",group:"him",kind:"fragrance"},
 {id:"ulonwabo",name:"Ulonwabo",short_description:"A joyful, elegant signature for her.",group:"her",kind:"fragrance"},
 {id:"ubulumko",name:"Ubulumko",short_description:"A warm, confident and expressive scent.",group:"her",kind:"fragrance"},
 {id:"ubunono",name:"Ubunono",short_description:"A refined signature with a graceful presence.",group:"her",kind:"fragrance"},
 {id:"reed-diffuser",name:"Reed Diffusers",short_description:"Bring considered fragrance into your space.",kind:"home"},
 {id:"linen-spray",name:"Linen Sprays",short_description:"A fresh finishing touch for everyday living.",kind:"home"},
 {id:"car-fragrance",name:"Car Fragrance",short_description:"Take your favourite atmosphere on the road.",kind:"home"},
 {id:"perfume-oils",name:"Pure Perfume Oils",short_description:"Explore oils for your own fragrance creations.",kind:"supplies"},
 {id:"bottles",name:"Bottles & Atomisers",short_description:"The essentials for bottling your own creations.",kind:"supplies"},
 {id:"starter-kit",name:"Perfume Starter Kit",short_description:"A practical starting point for your fragrance journey.",kind:"supplies"}
];
let activeFilter="all", searchTerm="";
function card(p){
 return '<article class="product-card"><div class="product-visual"><div class="mini-bottle">'+esc(p.name)+'</div></div><div class="product-info"><div><h3>'+esc(p.name)+'</h3><p>'+esc(p.short_description)+'</p><span class="preview-label">STOREFRONT PREVIEW</span></div><strong class="product-price">Coming soon</strong></div></article>';
}
function render(){
 const q=searchTerm.toLowerCase();
 const rows=catalogue.filter(p=>!q||(p.name+" "+p.short_description).toLowerCase().includes(q));
 const filtered=rows.filter(p=>p.kind==="fragrance"&&(activeFilter==="all"||p.group===activeFilter));
 document.getElementById("fragranceGrid").innerHTML=filtered.map(card).join("");
 document.getElementById("homeGrid").innerHTML=rows.filter(p=>p.kind==="home").map(card).join("");
 document.getElementById("supplyGrid").innerHTML=rows.filter(p=>p.kind==="supplies").map(card).join("");
}
document.querySelectorAll("[data-filter]").forEach(b=>b.addEventListener("click",()=>{
 document.querySelectorAll("[data-filter]").forEach(x=>x.classList.remove("active"));
 b.classList.add("active");activeFilter=b.dataset.filter;render();
}));
const search=document.getElementById("catalogueSearch");if(search)search.addEventListener("input",()=>{searchTerm=search.value.trim();render();});
const sort=document.getElementById("catalogueSort");if(sort)sort.addEventListener("change",()=>{const mode=sort.value;catalogue.sort((a,b)=>mode==="name-asc"?a.name.localeCompare(b.name):0);render();});
document.getElementById("year").textContent=new Date().getFullYear();
const menu=document.getElementById("menuToggle"),nav=document.getElementById("mainNav");
menu.addEventListener("click",()=>{const open=nav.classList.toggle("open");menu.setAttribute("aria-expanded",String(open));});
document.querySelectorAll("#mainNav a").forEach(a=>a.addEventListener("click",()=>nav.classList.remove("open")));
document.getElementById("cartTrigger").addEventListener("click",()=>{document.getElementById("cartDrawer").classList.add("open");document.getElementById("scrim").classList.add("show");});
function closeCart(){document.getElementById("cartDrawer").classList.remove("open");document.getElementById("scrim").classList.remove("show");}
document.getElementById("cartClose").addEventListener("click",closeCart);document.getElementById("scrim").addEventListener("click",closeCart);
render();
