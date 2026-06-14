/* ============================================================
   CYTECHNO — shared components & primitives
   ============================================================ */
const { useState, useEffect } = React;

/* navigation model ----------------------------------------- */
const NAV = [
  ["about","About"],["services","Services"],["portfolio","Portfolio"],
  ["blog","Blog"],["ideas","Ideas"],["products","Products"],
];

function go(path){ window.location.hash = "#/" + path; }
function L({to, className, children, ...rest}){
  return <a href={"#/"+to} className={className} {...rest}>{children}</a>;
}

/* placeholder image ---------------------------------------- */
function Ph({label, ratio="r43", dark=false, className=""}){
  return <div className={"ph "+ratio+(dark?" dark":"")+(className?" "+className:"")} data-label={label}></div>;
}

/* eyebrow --------------------------------------------------- */
function Eyebrow({children, dark=false}){
  return <span className={"eyebrow"+(dark?" on-dark":"")}>{children}</span>;
}

const Arrow = ({c}) => <span className="arw" aria-hidden="true">{c||"→"}</span>;

/* mark ------------------------------------------------------ */
function Mark({foot=false}){ return <span className="mark" aria-hidden="true"></span>; }

/* header ---------------------------------------------------- */
function Header({route}){
  const [open,setOpen]=useState(false);
  useEffect(()=>{ setOpen(false); },[route.section, route.slug]);
  const active = route.section || "home";
  return (
    <header className="site-head">
      <div className="wrap bar">
        <a href="#/" className="brand" aria-label="Cybertechnology — home">
          <Mark/>
          <span className="wm"><b>Cyber Technology</b><small>Secure · Scalable · Built to Last</small></span>
        </a>
        <button className="burger" aria-label="Menu" onClick={()=>setOpen(o=>!o)}>
          <span></span><span></span><span></span>
        </button>
        <nav className={"nav"+(open?" open":"")}>
          {NAV.map(([to,label])=>(
            <a key={to} href={"#/"+to} className={active===to?"active":""}>{label}</a>
          ))}
          <a href="#/contacts" className={"navcta"+(active==="contacts"?" active":"")}>Contact</a>
        </nav>
      </div>
    </header>
  );
}

/* footer ---------------------------------------------------- */
function Footer(){
  return (
    <footer className="site-foot">
      <div className="wrap">
        <div className="foot-top">
          <div className="foot-brand">
            <Mark/>
            <b>Cyber Technology</b>
            <p>Engineering secure digital infrastructure from Sofia, Bulgaria since 2004 — for government, healthcare and private enterprise.</p>
          </div>
          <div className="foot-col">
            <h4>Studio</h4>
            <a href="#/about">About</a>
            <a href="#/services">Services</a>
            <a href="#/portfolio">Portfolio</a>
            <a href="#/contacts">Contacts</a>
          </div>
          <div className="foot-col">
            <h4>Thinking</h4>
            <a href="#/blog">Blog</a>
            <a href="#/ideas">Ideas</a>
            <a href="#/products">Products</a>
            <a href="#/spec">Block Backlog ↗</a>
          </div>
          <div className="foot-col">
            <h4>Contact</h4>
            <a href="#/contacts">hello@cytechno.com</a>
            <a href="#/contacts">+359 2 000 0000</a>
            <a href="#/contacts">Sofia, Bulgaria</a>
          </div>
        </div>
        <div className="foot-bot">
          <span>© 2026 Cybertechnology · Nikolay Petrov, CEO</span>
          <span><a href="#/spec">Design Spec &amp; Block Backlog</a> · Free software where it counts</span>
        </div>
      </div>
    </footer>
  );
}

/* reusable section header ----------------------------------- */
function SectionHead({eyebrow, title, children, dark=false}){
  return (
    <div className="section-head">
      {eyebrow && <Eyebrow dark={dark}>{eyebrow}</Eyebrow>}
      {title && <h2 className="section-title" dangerouslySetInnerHTML={{__html:title}}></h2>}
      {children}
    </div>
  );
}

/* page hero (interior pages) -------------------------------- */
function PageHero({eyebrow, title, lead}){
  return (
    <section className="page-hero">
      <div className="wrap">
        <Eyebrow>{eyebrow}</Eyebrow>
        <h1>{title}</h1>
        {lead && <p className="lead mt-m" style={{maxWidth:"54ch"}}>{lead}</p>}
      </div>
    </section>
  );
}

/* CTA band -------------------------------------------------- */
function CtaBand({eyebrow="Get in touch", title="Let's build secure digital systems together", primary=["Start a project","contacts"], dark=true}){
  return (
    <section className={"section cta-band"+(dark?" section--dark":" section--alt")}>
      <div className="wrap stack" style={{alignItems:"center"}}>
        <Eyebrow dark={dark}>{eyebrow}</Eyebrow>
        <h2 dangerouslySetInnerHTML={{__html:title}}></h2>
        <div className="cta-actions">
          <a href={"#/"+primary[1]} className={"btn "+(dark?"btn--light":"btn--primary")}>{primary[0]} <Arrow/></a>
          <a href="#/portfolio" className={"btn "+(dark?"btn--light":"btn")}>View our work <Arrow/></a>
        </div>
      </div>
    </section>
  );
}

/* project card ---------------------------------------------- */
function ProjectCard({p}){
  return (
    <a className="card" href={"#/portfolio/"+p.slug}>
      <Ph label={p.img||("PROJECT · "+p.name.toUpperCase())} ratio="r43"/>
      <div className="body">
        <span className="cat">{p.cat}</span>
        <h3>{p.name}</h3>
        <p>{p.excerpt}</p>
        <span className="meta txtlink">Visit project <Arrow/></span>
      </div>
    </a>
  );
}

/* product card ---------------------------------------------- */
function ProductCard({p}){
  return (
    <a className="card" href={"#/products/"+p.slug}>
      <Ph label={p.img} ratio="r43"/>
      <div className="body">
        <span className="cat">{p.cat}</span>
        <h3>{p.name}</h3>
        <p>{p.short}</p>
        <div className="meta row" style={{justifyContent:"space-between",alignItems:"flex-end"}}>
          <span className={"price"+(p.price===0?" free":"")}>{p.priceLabel}{p.price!==0 && <small> one-time</small>}</span>
          <span className="txtlink">View <Arrow/></span>
        </div>
      </div>
    </a>
  );
}

Object.assign(window, {
  NAV, go, L, Ph, Eyebrow, Arrow, Mark, Header, Footer,
  SectionHead, PageHero, CtaBand, ProjectCard, ProductCard
});
