/* ============================================================
   CYTECHNO — pages: Ideas (listing+single), Products (listing+single), Contacts
   ============================================================ */
const { useState:mUS } = React;
const { Ph:MPh, Eyebrow:MEb, Arrow:MAr, SectionHead:MSH, PageHero:MPH, CtaBand:MCTA, ProductCard:MProd, Prose:MProse } = window;

/* =================== IDEAS LISTING ======================== */
function IdeasPage(){
  return (
    <div className="fadein">
      <MPH eyebrow="Ideas"
        title="A position on how software should serve the public"
        lead="Longer-form essays on free software, boring technology and infrastructure built to be owned in common — the thinking beneath everything we build."/>
      <section className="section">
        <div className="wrap">
          <div className="artlist">
            {window.IDEAS.map(i=>(
              <a key={i.slug} className="artrow" href={"#/ideas/"+i.slug}>
                <span className="date">Essay · {i.date}<br/>{i.read}</span>
                <div>
                  <h3>{i.title}</h3>
                  <p>{i.excerpt}</p>
                </div>
                <MAr/>
              </a>
            ))}
          </div>
        </div>
      </section>
      <MCTA eyebrow="Free software" title="We back our ideas with code"
        primary={["Explore products","products"]}/>
    </div>
  );
}

/* =================== SINGLE IDEA ========================== */
function IdeaSinglePage({slug}){
  const i = window.IDEAS.find(x=>x.slug===slug) || window.IDEAS[0];
  // link essay to a relevant product
  const product = window.PRODUCTS[0];
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap" style={{maxWidth:"860px",marginInline:"auto"}}>
          <a href="#/ideas" className="txtlink" style={{marginBottom:"18px"}}><span className="arw">←</span> All ideas</a>
          <MEb>Essay · {i.date}</MEb>
          <h1 style={{fontSize:"clamp(2.1rem,5.4vw,4.4rem)",marginTop:"14px"}}>{i.title}</h1>
        </div>
      </section>

      <section className="section">
        <div className="wrap" style={{maxWidth:"860px",marginInline:"auto"}}>
          <MProse body={i.body}/>

          {/* supported by us as free software */}
          <div className="fs-block mt-l">
            <div>
              <span className="tag-fs">Supported by us as free software</span>
              <h3>{product.name}</h3>
              <p>{product.short} We maintain it in the open so the argument above isn't just rhetoric — the code is there to inspect, fork and host yourself.</p>
            </div>
            <a href={"#/products/"+product.slug} className="btn btn--primary">View the product <MAr/></a>
          </div>

          <div className="attrib mt-l"><b>Nikolay Petrov</b><span>Founder &amp; CEO · Cybertechnology</span></div>
        </div>
      </section>

      <MCTA title="Build with a studio that means it"/>
    </div>
  );
}

/* =================== PRODUCTS LISTING ===================== */
function ProductsPage(){
  const [sort,setSort]=mUS("featured");   // featured | low | high
  const [band,setBand]=mUS("all");        // all | free | paid
  let list = window.PRODUCTS.slice();
  if(band==="free") list = list.filter(p=>p.price===0);
  if(band==="paid") list = list.filter(p=>p.price>0);
  if(sort==="low") list = list.slice().sort((a,b)=>a.price-b.price);
  if(sort==="high") list = list.slice().sort((a,b)=>b.price-a.price);

  return (
    <div className="fadein">
      <MPH eyebrow="Products"
        title="The tools beneath our platforms — released and supported"
        lead="Open-source infrastructure and fixed-scope product offerings. This catalogue grows over time; some tools are free software, others are commercial."/>

      <section className="section">
        <div className="wrap">
          {/* structured controls — DOGFOOD: sort/filter BY PRICE (a typed field) */}
          <div className="toolbar">
            <div className="group">
              <label>Price</label>
              <div className="seg">
                <button className={band==="all"?"on":""} onClick={()=>setBand("all")}>All</button>
                <button className={band==="free"?"on":""} onClick={()=>setBand("free")}>Free</button>
                <button className={band==="paid"?"on":""} onClick={()=>setBand("paid")}>Paid</button>
              </div>
            </div>
            <div className="group">
              <label>Sort by</label>
              <div className="seg">
                <button className={sort==="featured"?"on":""} onClick={()=>setSort("featured")}>Featured</button>
                <button className={sort==="low"?"on":""} onClick={()=>setSort("low")}>Price ↑</button>
                <button className={sort==="high"?"on":""} onClick={()=>setSort("high")}>Price ↓</button>
              </div>
            </div>
            <span className="cat" style={{color:"var(--ink-3)"}}>{list.length} products</span>
          </div>

          <div className="grid cols-3 mt-m">
            {list.map(p=> <MProd key={p.slug} p={p}/>)}
          </div>

          <p className="muted mt-l" style={{fontSize:".82rem",maxWidth:"60ch",borderTop:"1px solid var(--line)",paddingTop:"18px"}}>
            <strong>Spec note —</strong> sorting and filtering by <em>price</em> requires price to be a queryable typed
            field, not free-text inside a block. See the <a href="#/spec" className="red" style={{fontWeight:600}}>Block Backlog</a> for the structured-content verdict.
          </p>
        </div>
      </section>

      <MCTA eyebrow="Need something built?" title="We also build to order"/>
    </div>
  );
}

/* =================== SINGLE PRODUCT ======================= */
function ProductSinglePage({slug}){
  const p = window.PRODUCTS.find(x=>x.slug===slug) || window.PRODUCTS[0];
  const others = window.PRODUCTS.filter(x=>x.slug!==p.slug).slice(0,3);
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap">
          <a href="#/products" className="txtlink" style={{marginBottom:"18px"}}><span className="arw">←</span> All products</a>
          <div className="grid cols-2" style={{gap:"clamp(28px,4vw,56px)",alignItems:"center",marginTop:"10px"}}>
            <div>
              <span className="cat">{p.cat}</span>
              <h1 style={{marginTop:"12px"}}>{p.name}</h1>
              <p className="lead mt-m" style={{maxWidth:"46ch"}}>{p.short}</p>
              <div className="row gap-m mt-l" style={{gap:"24px",alignItems:"center",flexWrap:"wrap"}}>
                <span className={"price"+(p.price===0?" free":"")} style={{fontSize:"2.4rem"}}>{p.priceLabel}
                  {p.price!==0 && <small> one-time</small>}</span>
                <a href="#/contacts" className="btn btn--primary">{p.cta.label} <MAr/></a>
              </div>
            </div>
            <MPh label={p.img} ratio="r43"/>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"start"}}>
          <div>
            <MSH eyebrow="Description" title="What it is"/>
            <p className="lead">{p.description}</p>
          </div>
          <div>
            <MSH eyebrow="Structured features" title="Specification"/>
            {/* DOGFOOD: typed feature pairs — key/value structured fields */}
            <ul className="feature-list" style={{borderTop:"1px solid var(--line)"}}>
              {p.features.map(([k,v],i)=>(
                <li key={i}><b>{k}</b><span>{v}</span></li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap cta-band stack" style={{alignItems:"center",textAlign:"center"}}>
          <MEb>{p.cta.kind}</MEb>
          <h2 style={{fontFamily:"'Barlow Condensed',sans-serif",fontWeight:700,textTransform:"uppercase",fontSize:"clamp(1.8rem,4vw,3.2rem)",lineHeight:".98",margin:"16px 0 26px",maxWidth:"18ch"}}>
            {p.cta.kind==="View repo" ? "Inspect, fork and host it yourself" : p.cta.kind==="Try it" ? "Put it in your pipeline today" : "Tell us about your platform"}
          </h2>
          <a href="#/contacts" className="btn btn--primary">{p.cta.label} <MAr/></a>
        </div>
      </section>

      <section className="section">
        <div className="wrap">
          <MSH eyebrow="More products" title="From the catalogue">
            <a href="#/products" className="btn btn--ghost" style={{alignSelf:"flex-start"}}>All products <MAr/></a>
          </MSH>
          <div className="grid cols-3">
            {others.map(o=> <MProd key={o.slug} p={o}/>)}
          </div>
        </div>
      </section>
    </div>
  );
}

/* =================== CONTACTS ============================= */
function ContactsPage(){
  const [v,setV]=mUS({name:"",email:"",message:""});
  const [err,setErr]=mUS({});
  const [sent,setSent]=mUS(false);
  const set=(k)=>(e)=>{ setV(s=>({...s,[k]:e.target.value})); setErr(s=>({...s,[k]:""})); };
  const submit=(e)=>{
    e.preventDefault();
    const er={};
    if(!v.name.trim()) er.name="Please enter your name.";
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v.email)) er.email="Enter a valid email address.";
    if(v.message.trim().length<10) er.message="A little more detail, please (10+ characters).";
    setErr(er);
    if(Object.keys(er).length===0) setSent(true);
  };
  return (
    <div className="fadein">
      <MPH eyebrow="Contacts"
        title="Start a project, or just ask a hard question"
        lead="Tell us what needs to run reliably for the next decade. We reply to every serious enquiry — usually within a working day."/>

      <section className="section">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"start"}}>
          {/* form */}
          <div>
            <MSH eyebrow="Send a message" title="Project enquiry"/>
            {sent ? (
              <div className="form-ok">
                <span className="cond red" style={{fontSize:"1.6rem",lineHeight:1}}>✓</span>
                <div>
                  <b>Message received</b>
                  <p className="muted" style={{margin:"6px 0 0",fontSize:".92rem"}}>Thanks, {v.name.split(" ")[0]}. We'll be in touch at {v.email} within one working day.</p>
                </div>
              </div>
            ) : (
              <form className="form" onSubmit={submit} noValidate>
                <div className={"field"+(err.name?" err":"")}>
                  <label>Your name</label>
                  <input value={v.name} onChange={set("name")} placeholder="Nikolay Petrov"/>
                  <span className="msg">{err.name}</span>
                </div>
                <div className={"field"+(err.email?" err":"")}>
                  <label>Email</label>
                  <input value={v.email} onChange={set("email")} placeholder="you@organisation.bg"/>
                  <span className="msg">{err.email}</span>
                </div>
                <div className={"field"+(err.message?" err":"")}>
                  <label>What do you need built?</label>
                  <textarea rows="5" value={v.message} onChange={set("message")} placeholder="A short description of the platform, the sector and the constraints…"></textarea>
                  <span className="msg">{err.message}</span>
                </div>
                <button type="submit" className="btn btn--solid" style={{alignSelf:"flex-start"}}>Send enquiry <MAr/></button>
                <p className="muted" style={{fontSize:".78rem"}}>
                  <strong>Spec note —</strong> this contact form needs a <code style={{fontFamily:"ui-monospace,monospace",background:"#f3f3f1",padding:"1px 5px"}}>contact-form</code> block that doesn't exist yet. See the <a href="#/spec" className="red" style={{fontWeight:600}}>Block Backlog</a>.
                </p>
              </form>
            )}
          </div>

          {/* direct details */}
          <div>
            <MSH eyebrow="Direct" title="Reach us"/>
            <div className="cdetail"><span>Email</span><b>hello@cytechno.com</b></div>
            <div className="cdetail"><span>Phone</span><b>+359 2 000 0000</b></div>
            <div className="cdetail"><span>Studio</span><b>Sofia, Bulgaria</b></div>
            <div className="cdetail"><span>Hours</span><b>Mon–Fri · 09:00–18:00 EET</b></div>
            <MPh label="MAP · SOFIA STUDIO LOCATION" ratio="r43" className="mt-m"/>
          </div>
        </div>
      </section>
    </div>
  );
}

Object.assign(window, { IdeasPage, IdeaSinglePage, ProductsPage, ProductSinglePage, ContactsPage });
