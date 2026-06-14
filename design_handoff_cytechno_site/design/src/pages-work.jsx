/* ============================================================
   CYTECHNO — pages: Portfolio (listing+single), Blog (listing+single)
   ============================================================ */
const { useState:_uS } = React;
const { Ph:WPh, Eyebrow:WEb, Arrow:WAr, SectionHead:WSH, PageHero:WPH, CtaBand:WCTA, ProjectCard:WPC } = window;

/* ---------- prose renderer (shared by blog + ideas) -------- */
function Prose({body}){
  return (
    <div className="prose">
      {body.map((b,i)=>{
        if(b.h) return <h2 key={i}>{b.h}</h2>;
        if(b.q) return <blockquote key={i}>{b.q}</blockquote>;
        if(b.ul) return <ul key={i}>{b.ul.map((li,j)=><li key={j}>{li}</li>)}</ul>;
        return <p key={i}>{b.p}</p>;
      })}
    </div>
  );
}

/* =================== PORTFOLIO LISTING ==================== */
function PortfolioPage(){
  const cats = ["All", ...Array.from(new Set(window.PROJECTS.map(p=>p.cat.split(" · ")[0])))];
  const [f,setF]=_uS("All");
  const list = f==="All" ? window.PROJECTS : window.PROJECTS.filter(p=>p.cat.split(" · ")[0]===f);
  return (
    <div className="fadein">
      <WPH eyebrow="Portfolio"
        title="Platforms in production across government, healthcare and culture"
        lead="A selection of the systems we have designed, built and still maintain. Each was engineered to run reliably for years — many still do."/>

      <section className="section">
        <div className="wrap">
          <div className="toolbar">
            <div className="group">
              <label>Filter</label>
              <div className="seg">
                {cats.map(c=>(
                  <button key={c} className={f===c?"on":""} onClick={()=>setF(c)}>{c}</button>
                ))}
              </div>
            </div>
            <span className="cat" style={{color:"var(--ink-3)"}}>{list.length} projects</span>
          </div>
          <div className="grid cols-3 mt-m">
            {list.map(p=> <WPC key={p.slug} p={p}/>)}
          </div>
        </div>
      </section>

      <WCTA title="Have a platform that needs to last?"/>
    </div>
  );
}

/* =================== SINGLE PROJECT ======================= */
function ProjectSinglePage({slug}){
  const idx = Math.max(0, window.PROJECTS.findIndex(p=>p.slug===slug));
  const p = window.PROJECTS[idx];
  const next = window.PROJECTS[(idx+1)%window.PROJECTS.length];
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap">
          <a href="#/portfolio" className="txtlink" style={{marginBottom:"18px"}}><span className="arw">←</span> All projects</a>
          <span className="cat">{p.cat}</span>
          <h1 style={{marginTop:"12px"}}>{p.name}</h1>
          <div className="wrap-flex gap-l mt-m" style={{gap:"40px"}}>
            <div className="cdetail" style={{borderTop:0,padding:0}}><span>Client</span><b>{p.client}</b></div>
            <div className="cdetail" style={{borderTop:0,padding:0}}><span>Year</span><b>{p.year}</b></div>
            <div className="cdetail" style={{borderTop:0,padding:0}}><span>Sector</span><b>{p.cat.split(" · ")[0]}</b></div>
          </div>
        </div>
      </section>

      <WPh label={"PROJECT · "+p.name.toUpperCase()+" · LEAD IMAGE"} ratio="r219"/>

      <section className="section">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"start"}}>
          <div>
            <WSH eyebrow="Overview" title="The brief"/>
            <p className="lead">{p.overview}</p>
            <div className="tags mt-m">{p.tags.map(t=><span key={t} className="tag">{t}</span>)}</div>
          </div>
          <div className="stack gap-l">
            <div><h3 className="cond" style={{fontSize:"1.4rem",marginBottom:"10px"}}>Challenge</h3><p className="muted">{p.challenge}</p></div>
            <div><h3 className="cond" style={{fontSize:"1.4rem",marginBottom:"10px"}}>Approach</h3><p className="muted">{p.approach}</p></div>
            <div><h3 className="cond" style={{fontSize:"1.4rem",marginBottom:"10px"}}>Outcome</h3><p className="muted">{p.outcome}</p></div>
          </div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap">
          <WSH eyebrow="Gallery" title="Selected screens"/>
          <div className="grid cols-3">
            <WPh label="SCREEN · HOME" ratio="r32"/>
            <WPh label="SCREEN · LISTING" ratio="r32"/>
            <WPh label="SCREEN · DETAIL" ratio="r32"/>
          </div>
          <WPh label="SCREEN · FULL-WIDTH VIEW" ratio="r219" className="mt-m"/>
        </div>
      </section>

      <section className="section">
        <div className="wrap">
          <a href={"#/portfolio/"+next.slug} className="rowitem" style={{borderTop:"1px solid var(--line)",borderBottom:"1px solid var(--line)",gridTemplateColumns:"auto 1fr 40px",alignItems:"center"}}>
            <span className="num">Next</span>
            <div><span className="cat">{next.cat}</span><h3 style={{marginTop:"6px"}}>{next.name}</h3></div>
            <WAr/>
          </a>
        </div>
      </section>

      <WCTA/>
    </div>
  );
}

/* =================== BLOG LISTING ========================= */
function BlogPage(){
  return (
    <div className="fadein">
      <WPH eyebrow="Blog"
        title="Field notes on building durable platforms"
        lead="Everyday writing from the studio on web performance, security and the discipline of keeping mission-critical systems green."/>
      <section className="section">
        <div className="wrap">
          <div className="artlist">
            {window.BLOG.map(b=>(
              <a key={b.slug} className="artrow" href={"#/blog/"+b.slug}>
                <span className="date">{b.date}<br/>{b.read}</span>
                <div>
                  <h3>{b.title}</h3>
                  <p>{b.excerpt}</p>
                </div>
                <WAr/>
              </a>
            ))}
          </div>
        </div>
      </section>
      <WCTA eyebrow="Work with us" title="Prefer to talk it through?"/>
    </div>
  );
}

/* =================== SINGLE POST ========================== */
function PostSinglePage({slug}){
  const idx = Math.max(0, window.BLOG.findIndex(b=>b.slug===slug));
  const b = window.BLOG[idx];
  const related = window.BLOG.filter(x=>x.slug!==b.slug).slice(0,2);
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap" style={{maxWidth:"860px",marginInline:"auto"}}>
          <a href="#/blog" className="txtlink" style={{marginBottom:"18px"}}><span className="arw">←</span> All articles</a>
          <h1 style={{fontSize:"clamp(2.1rem,5vw,4rem)"}}>{b.title}</h1>
          <div className="wrap-flex mt-m" style={{gap:"28px"}}>
            <span className="cat" style={{color:"var(--ink-3)"}}>{b.date}</span>
            <span className="cat" style={{color:"var(--ink-3)"}}>{b.author}</span>
            <span className="cat" style={{color:"var(--ink-3)"}}>{b.read} read</span>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="wrap" style={{maxWidth:"860px",marginInline:"auto"}}>
          <Prose body={b.body}/>
          <div className="attrib mt-l"><b>{b.author}</b><span>Founder &amp; CEO · Cybertechnology</span></div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap">
          <WSH eyebrow="Related" title="Keep reading"/>
          <div className="artlist" style={{borderTop:"1px solid var(--line)"}}>
            {related.map(r=>(
              <a key={r.slug} className="artrow" href={"#/blog/"+r.slug}>
                <span className="date">{r.date}</span>
                <div><h3>{r.title}</h3><p>{r.excerpt}</p></div>
                <WAr/>
              </a>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}

Object.assign(window, { Prose, PortfolioPage, ProjectSinglePage, BlogPage, PostSinglePage });
