/* ============================================================
   CYTECHNO — pages: Home, About, Services landing, Single Service
   ============================================================ */
const { Ph:_Ph, Eyebrow:_Eb, Arrow:_Ar, SectionHead:_SH, PageHero:_PH, CtaBand:_CTA, ProjectCard:_PC } = window;

/* =================== HOME ================================== */
function HomePage(){
  const feat = window.PROJECTS.slice(0,3);
  const ideas = window.IDEAS.slice(0,2);
  const posts = window.BLOG.slice(0,3);
  return (
    <div className="fadein">
      {/* hero */}
      <section className="hero">
        <_Ph label="HERO BACKGROUND · TECHNICAL NETWORK IMAGE" ratio="r219" className="ph-bg"/>
        <div className="wrap">
          <_Eb>Cybertechnology · Est. 2004 · Sofia</_Eb>
          <h1 className="mt-m">Engineering <span className="red">secure digital</span> infrastructure</h1>
          <p className="lead mt-m" style={{maxWidth:"52ch"}}>
            Two decades of mission-critical web development and IT solutions — trusted by Bulgarian
            government institutions, healthcare organisations and private enterprises to deliver platforms
            that are secure, scalable and built to last.
          </p>
          <div className="cta-actions mt-l" style={{justifyContent:"flex-start"}}>
            <a href="#/contacts" className="btn btn--primary">Start a project <_Ar/></a>
            <a href="#/portfolio" className="btn btn--ghost">View our work <_Ar/></a>
          </div>
        </div>
      </section>

      {/* about teaser */}
      <section className="section">
        <div className="wrap grid cols-2" style={{alignItems:"start",gap:"clamp(34px,5vw,72px)"}}>
          <div>
            <_SH eyebrow="Who we are" title="Two decades of<br><span class='red'>technical excellence</span>"/>
            <p className="lead">Cybertechnology is a professional web development and IT infrastructure company
              headquartered in Sofia. Since 2004 we have engineered systems that operate reliably, securely and
              at scale for clients who cannot afford downtime.</p>
            <p className="muted mt-s">We design, build and maintain enterprise platforms, secure government portals,
              healthcare information systems and scalable architectures for organisations that require long-term,
              trusted partnerships rather than one-off projects.</p>
            <div className="attrib mt-m">
              <b>Nikolay Petrov</b><span>Founder &amp; CEO</span>
            </div>
            <a href="#/about" className="btn btn--ghost mt-m">More about the studio <_Ar/></a>
          </div>
          <div className="stat-grid">
            <div className="stat"><b>20+</b><span>Years on market</span></div>
            <div className="stat"><b>150+</b><span>Projects delivered</span></div>
            <div className="stat"><b>Gov &amp; Private</b><span>Trusted sectors</span></div>
            <div className="stat"><b>Long-Term</b><span>Client partnerships</span></div>
          </div>
        </div>
      </section>

      {/* services overview */}
      <section className="section section--alt">
        <div className="wrap">
          <_SH eyebrow="What we do" title="Core capabilities">
            <a href="#/services" className="btn btn--ghost" style={{alignSelf:"flex-start"}}>All services <_Ar/></a>
          </_SH>
          <div className="rowlist" style={{borderTop:"1px solid var(--line)"}}>
            {window.SERVICES.slice(0,3).map(s=>(
              <a key={s.slug} className="rowitem" href={"#/services/"+s.slug}>
                <span className="num">{s.n}</span>
                <h3>{s.title}</h3>
                <p>{s.summary}</p>
                <_Ar/>
              </a>
            ))}
          </div>
        </div>
      </section>

      {/* selected portfolio */}
      <section className="section">
        <div className="wrap">
          <_SH eyebrow="Our work" title="Selected projects">
            <a href="#/portfolio" className="btn btn--ghost" style={{alignSelf:"flex-start"}}>View all projects <_Ar/></a>
          </_SH>
          <div className="grid cols-3">
            {feat.map(p=> <_PC key={p.slug} p={p}/>)}
          </div>
        </div>
      </section>

      {/* ideas + blog teaser */}
      <section className="section section--alt">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,64px)"}}>
          <div>
            <_SH eyebrow="Ideas" title="Visionary essays"/>
            <div className="artlist" style={{borderTop:"1px solid var(--line)"}}>
              {ideas.map(i=>(
                <a key={i.slug} className="artrow" href={"#/ideas/"+i.slug} style={{gridTemplateColumns:"1fr 40px"}}>
                  <div>
                    <h3>{i.title}</h3>
                    <p>{i.excerpt}</p>
                  </div>
                  <_Ar/>
                </a>
              ))}
            </div>
            <a href="#/ideas" className="btn btn--ghost mt-m">All ideas <_Ar/></a>
          </div>
          <div>
            <_SH eyebrow="Blog" title="From the studio"/>
            <div className="artlist" style={{borderTop:"1px solid var(--line)"}}>
              {posts.map(b=>(
                <a key={b.slug} className="artrow" href={"#/blog/"+b.slug} style={{gridTemplateColumns:"1fr 40px",padding:"24px 0"}}>
                  <div>
                    <span className="date">{b.date}</span>
                    <h3 style={{fontSize:"1.35rem",margin:"8px 0 0"}}>{b.title}</h3>
                  </div>
                  <_Ar/>
                </a>
              ))}
            </div>
            <a href="#/blog" className="btn btn--ghost mt-m">All articles <_Ar/></a>
          </div>
        </div>
      </section>

      <_CTA/>
    </div>
  );
}

/* =================== ABOUT ================================= */
function AboutPage(){
  return (
    <div className="fadein">
      <_PH eyebrow="About the studio"
        title="We build infrastructure meant to outlive the trend"
        lead="Cybertechnology is a Sofia-based web development and IT infrastructure company. Since 2004 we have engineered platforms for organisations that cannot afford downtime — and cannot afford to be locked in."/>

      <section className="section">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"start"}}>
          <div>
            <_SH eyebrow="Story &amp; approach" title="Deliberate, not fashionable"/>
            <p className="lead">Our approach is deliberate: clean code, proven technologies, no bloated frameworks —
              purpose-built digital infrastructure engineered to stand the test of time.</p>
            <p className="muted mt-s">We design, build and maintain enterprise platforms, secure government portals,
              healthcare information systems and scalable web architectures. Most of our relationships are measured
              in years, not deliverables — because the systems we build are meant to run for a decade or more.</p>
            <p className="muted mt-s">We favour long-term partnerships over one-off projects. That bias shows up in
              everything: documentation, knowledge transfer, and a roadmap our clients own outright.</p>
          </div>
          <_Ph label="STUDIO · TEAM / OFFICE IMAGE" ratio="r43"/>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap">
          <_SH eyebrow="What we value" title="Four commitments"/>
          <div className="grid cols-4">
            {[
              ["Security first","TLS, headers and isolation hardened from day one — the boring layers that keep platforms standing."],
              ["Built to last","Proven, dull, well-understood tools chosen for thirty-year decisions, not launch-day benchmarks."],
              ["Owned, not rented","Clients own their infrastructure and their roadmap — never stranded inside a vendor's product."],
              ["Measured, not assumed","PageSpeed, accessibility and audits are measured and enforced, not promised."],
            ].map(([t,d],i)=>(
              <div key={i} className="stack gap-s" style={{borderTop:"2px solid var(--red)",paddingTop:"20px"}}>
                <h3 className="cond" style={{fontSize:"1.45rem"}}>{t}</h3>
                <p className="muted" style={{fontSize:".92rem"}}>{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* free-software philosophy */}
      <section className="section">
        <div className="wrap">
          <div className="fs-block">
            <div>
              <span className="tag-fs">Free-software philosophy</span>
              <h3>Software that runs the state should be inspectable by the public it serves</h3>
              <p>We release the tools beneath our platforms as free software and support them as such. It is the only
                honest way to build infrastructure meant to outlive any single vendor — including us. Read the argument
                in full in our Ideas essays.</p>
            </div>
            <a href="#/ideas/free-software-civic-infrastructure" className="btn btn--primary">Read the essay <_Ar/></a>
          </div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"center"}}>
          <div className="stat-grid">
            <div className="stat"><b>20+</b><span>Years on market</span></div>
            <div className="stat"><b>150+</b><span>Projects delivered</span></div>
            <div className="stat"><b>Sofia</b><span>Headquartered in Bulgaria</span></div>
            <div className="stat"><b>2004</b><span>Founded</span></div>
          </div>
          <div>
            <_SH eyebrow="Leadership" title="Run by engineers"/>
            <p className="lead">The studio is led by founder and CEO Nikolay Petrov, who has guided Cybertechnology
              from a small Sofia practice into a trusted partner for national institutions.</p>
            <div className="attrib mt-m"><b>Nikolay Petrov</b><span>Founder &amp; CEO</span></div>
          </div>
        </div>
      </section>

      <_CTA title="Let's build something that lasts"/>
    </div>
  );
}

/* =================== SERVICES LANDING ===================== */
function ServicesPage(){
  return (
    <div className="fadein">
      <_PH eyebrow="Services"
        title="Engineering, security and the discipline to keep them green"
        lead="From custom platforms to TLS hardening and long-term support — each engagement starts from the data model and ends in a partnership, not a deliverable."/>

      <section className="section">
        <div className="wrap">
          <div className="rowlist">
            {window.SERVICES.map(s=>(
              <a key={s.slug} className="rowitem" href={"#/services/"+s.slug}>
                <span className="num">{s.n}</span>
                <h3>{s.title}</h3>
                <p>{s.summary}</p>
                <_Ar/>
              </a>
            ))}
          </div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap">
          <_SH eyebrow="How we work" title="A four-step partnership"/>
          <div className="grid cols-4">
            {window.PROCESS.map(s=>(
              <div key={s.n} className="stack gap-s" style={{borderTop:"2px solid var(--red)",paddingTop:"20px"}}>
                <span className="num cond" style={{color:"var(--ink-3)",fontSize:"1.1rem"}}>{s.n}</span>
                <h3 className="cond" style={{fontSize:"1.45rem"}}>{s.t}</h3>
                <p className="muted" style={{fontSize:".92rem"}}>{s.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <_CTA title="Tell us what needs to run for a decade"/>
    </div>
  );
}

/* =================== SINGLE SERVICE ======================= */
function ServiceSinglePage({slug}){
  const s = window.SERVICES.find(x=>x.slug===slug) || window.SERVICES[0];
  const related = window.PROJECTS.slice(0,3);
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap">
          <a href="#/services" className="txtlink" style={{marginBottom:"18px"}}><span className="arw">←</span> All services</a>
          <_Eb>Service · {s.n}</_Eb>
          <h1>{s.title}</h1>
          <p className="lead mt-m" style={{maxWidth:"56ch"}}>{s.summary}</p>
        </div>
      </section>

      <section className="section">
        <div className="wrap grid cols-2" style={{gap:"clamp(34px,5vw,72px)",alignItems:"start"}}>
          <div>
            <_SH eyebrow="What's included" title="Scope"/>
            <ul className="feature-list" style={{borderTop:"1px solid var(--line)"}}>
              {s.included.map((f,i)=>(
                <li key={i}><b>{f}</b><span>{String(i+1).padStart(2,"0")}</span></li>
              ))}
            </ul>
          </div>
          <div>
            <_SH eyebrow="Our approach" title="How we deliver it"/>
            <p className="lead">{s.approach}</p>
            <_Ph label="SERVICE · SUPPORTING DIAGRAM" ratio="r43" className="mt-m"/>
          </div>
        </div>
      </section>

      <section className="section section--alt">
        <div className="wrap">
          <_SH eyebrow="Related work" title="In production">
            <a href="#/portfolio" className="btn btn--ghost" style={{alignSelf:"flex-start"}}>All projects <_Ar/></a>
          </_SH>
          <div className="grid cols-3">
            {related.map(p=> <_PC key={p.slug} p={p}/>)}
          </div>
        </div>
      </section>

      <_CTA title={"Need "+s.title.toLowerCase()+"?"}/>
    </div>
  );
}

Object.assign(window, { HomePage, AboutPage, ServicesPage, ServiceSinglePage });
