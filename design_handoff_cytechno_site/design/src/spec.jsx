/* ============================================================
   CYTECHNO — Spec / Block Backlog (sections → CMS blocks + verdict)
   ============================================================ */
const { Eyebrow:SEb, Arrow:SAr, SectionHead:SSH } = window;

/* block status legend:  ok = exists / generic   miss = MISSING_BACKEND   arch = architectural gap */
const TEMPLATES = [
  { t:"Home (landing)", type:"Page · block editor", note:"⚠ hits BUG-001 on homepage setting", rows:[
    ["Hero","Big condensed type, positioning statement, red accent","hero","ok"],
    ["About teaser","Two-col text + 2×2 stat grid","text + stat-grid","ok"],
    ["Services overview","Numbered editorial row list","row-list","ok"],
    ["Selected portfolio","Featured project cards (query)","post-query (Portfolio)","ok"],
    ["Ideas / Blog teaser","Latest posts, two columns","post-query","ok"],
    ["Contact CTA","Band + buttons","cta-band","ok"],
  ]},
  { t:"About", type:"Page · block editor", rows:[
    ["Mission intro","Lead + editorial body","text","ok"],
    ["Values grid","Four red-rule columns","feature-grid","ok"],
    ["Free-software block","Bordered callout → Ideas","callout","ok"],
    ["Leadership / stats","Stat grid + attribution","stat-grid","ok"],
  ]},
  { t:"Services landing", type:"Page · landing + Services category", rows:[
    ["Intro","Page hero","page-hero","ok"],
    ["Services list","Each links to single service (query)","post-query (Services)","ok"],
    ["How we work","Four-step process grid","feature-grid","ok"],
  ]},
  { t:"Single Service", type:"Post · Services category", rows:[
    ["Service hero","Title + summary","page-hero","ok"],
    ["What's included","Typed scope list","feature-list","ok"],
    ["Approach","Text + image","text + media","ok"],
    ["Related work","Project query","post-query","ok"],
  ]},
  { t:"Portfolio listing", type:"Portfolio category", rows:[
    ["Intro","Page hero","page-hero","ok"],
    ["Filter","Filter by sector taxonomy","taxonomy-filter","ok"],
    ["Project grid","Featured image, title, year, tags","post-grid","ok"],
  ]},
  { t:"Single Project", type:"Post · Portfolio category", rows:[
    ["Project hero","Title, client, year, lead image","page-hero + meta","ok"],
    ["Overview + meta","Client/year/sector typed meta","post-meta fields","arch"],
    ["Challenge / Approach / Outcome","Structured editorial trio","text","ok"],
    ["Image gallery","Grid + full-width media","gallery","ok"],
    ["Next project","Auto-linked sibling","post-nav","ok"],
  ]},
  { t:"Blog listing + Single Post", type:"Blog category", rows:[
    ["Article list","Date, title, excerpt","post-list","ok"],
    ["Post body","Editorial prose, quotes, headings","prose blocks","ok"],
    ["Author + date","Typed post meta","post-meta","ok"],
    ["Related posts","Same-category query","post-query","ok"],
  ]},
  { t:"Ideas listing + Single Idea", type:"Ideas category", rows:[
    ["Essay list","Visionary framing + list","post-list","ok"],
    ["Essay body","Long-form prose","prose blocks","ok"],
    ["“Supported as free software”","Callout linking to a Product","relation → Product","arch"],
    ["CTA","Band","cta-band","ok"],
  ]},
  { t:"Products listing", type:"Products category", note:"the structured-content dogfood", rows:[
    ["Intro","Page hero","page-hero","ok"],
    ["Product grid","Image, name, PRICE, description","product-card","miss"],
    ["Sort / filter by PRICE","Query & order by typed field","field-query (price)","arch"],
  ]},
  { t:"Single Product", type:"Post · Products category", rows:[
    ["Product hero","Image, name, PRICE","product-hero","miss"],
    ["Description","Editorial text","text","ok"],
    ["Structured features","Typed key/value field list","field-set (features[])","arch"],
    ["CTA button","Typed CTA {label, kind, url}","field (cta)","arch"],
  ]},
  { t:"Contacts", type:"Page · block editor", note:"dogfood gap", rows:[
    ["Intro","Page hero","page-hero","ok"],
    ["Contact form","Name / email / message + validation","contact-form","miss"],
    ["Direct details","Typed contact info","text / fields","ok"],
    ["Location","Map embed","media / embed","ok"],
  ]},
];

const PILL = { ok:["ok","Exists / generic"], miss:["miss","Missing backend"], arch:["arch","Architectural gap"] };

function SpecPage(){
  const counts = TEMPLATES.flatMap(t=>t.rows).reduce((a,[,,,s])=>(a[s]=(a[s]||0)+1,a),{});
  return (
    <div className="fadein">
      <section className="page-hero">
        <div className="wrap">
          <SEb>Track H · Design Spec</SEb>
          <h1>Block backlog &amp; structured-content verdict</h1>
          <p className="lead mt-m" style={{maxWidth:"60ch"}}>
            The visual spec mapped to the CMS block model. Every section above resolves to a block; the ones flagged
            below are what the rebuild must add. This is the input to the Claude Code handoff — reproduce in the Blade
            static stack, no SSR, preserve static publish &amp; PageSpeed 100.
          </p>
        </div>
      </section>

      {/* legend + tallies */}
      <section className="section section--tight section--alt">
        <div className="wrap row" style={{justifyContent:"space-between",flexWrap:"wrap",gap:"18px"}}>
          <div className="spec-legend">
            <span className="pill ok">Exists / generic · {counts.ok||0}</span>
            <span className="pill miss">Missing backend · {counts.miss||0}</span>
            <span className="pill arch">Architectural gap · {counts.arch||0}</span>
          </div>
          <span className="cat" style={{color:"var(--ink-3)"}}>11 templates · shared header + footer</span>
        </div>
      </section>

      {/* per-template mapping */}
      <section className="section">
        <div className="wrap">
          <SSH eyebrow="Section → block" title="Template-by-template mapping"/>
          {TEMPLATES.map((tpl,i)=>(
            <div key={i} className="spec-tpl">
              <div className="h">
                <b>{tpl.t}</b>
                <span>{tpl.type}{tpl.note?" · "+tpl.note:""}</span>
              </div>
              {tpl.rows.map(([sec,desc,block,st],j)=>(
                <div key={j} className="blockrow">
                  <div className="bn">{sec}</div>
                  <div className="bd">{desc} → <code>{block}</code></div>
                  <span className={"pill "+PILL[st][0]}>{PILL[st][1]}</span>
                </div>
              ))}
            </div>
          ))}
        </div>
      </section>

      {/* block backlog summary */}
      <section className="section section--alt">
        <div className="wrap">
          <SSH eyebrow="Backlog" title="New blocks to build"/>
          <div className="grid cols-2">
            {[
              ["contact-form","MISSING_BACKEND","Name / email / message with validation + spam guard. Needed on Contacts. No equivalent block exists today."],
              ["product-card / product-hero","MISSING_BACKEND","Renders image, name, price, short description. The display half of the Products problem — see verdict."],
              ["post-meta (typed)","ARCHITECTURAL","Project client/year/sector and post author/date as queryable fields, not free text."],
              ["relation field","ARCHITECTURAL","Idea → Product link (“supported as free software”). Cross-type reference the model can query."],
            ].map(([n,tag,d],k)=>(
              <div key={k} className="stack gap-s" style={{borderTop:"2px solid var(--red)",paddingTop:"18px"}}>
                <div className="row" style={{justifyContent:"space-between",alignItems:"baseline",gap:"10px"}}>
                  <code style={{fontFamily:"ui-monospace,monospace",fontSize:".9rem",color:"var(--red-ink)"}}>{n}</code>
                  <span className={"pill "+(tag==="MISSING_BACKEND"?"miss":"arch")}>{tag==="MISSING_BACKEND"?"Missing":"Architectural"}</span>
                </div>
                <p className="muted" style={{fontSize:".92rem"}}>{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* the (a)/(b) verdict */}
      <section className="section">
        <div className="wrap" style={{maxWidth:"940px"}}>
          <SSH eyebrow="The deciding test" title="Structured fields: <span class='red'>(a) block</span> or (b) content type?"/>
          <p className="lead" style={{marginBottom:"26px"}}>
            Products carry typed fields — <strong>price, features[], cta</strong> — that generic blocks don't model.
            The brief asks for <strong>sort &amp; filter by price</strong> on the listing on purpose, to force the question.
          </p>
          <div className="verdict">
            <div className="opt">
              <span className="k">a</span>
              <div>
                <h4>Product-card block</h4>
                <p>A block whose <code style={{fontFamily:"ui-monospace,monospace",background:"#f3f3f1",padding:"1px 5px"}}>data</code> JSON holds <code style={{fontFamily:"ui-monospace,monospace",background:"#f3f3f1",padding:"1px 5px"}}>{"{price, features[], cta}"}</code>.
                  Fits the existing block-as-JSON model; no schema change. Sufficient <em>if you only need to display</em> the fields.</p>
              </div>
            </div>
            <div className="opt win">
              <span className="k">b</span>
              <div>
                <h4>Structured content type — the verdict ✓</h4>
                <p>Because the listing must <strong>sort and filter by price</strong> — and editors must fill typed fields in
                  the admin — price has to be a first-class, queryable field at the model/DB level, with schema, validation
                  and field-editing UI. That does <strong>not exist</strong> in today's <code style={{fontFamily:"ui-monospace,monospace",background:"#f3f3f1",padding:"1px 5px"}}>pages · posts · categories · blocks</code> model.
                  This is the big architectural gap, gated behind Track 0 (builder hierarchy).</p>
              </div>
            </div>
          </div>
          <p className="muted mt-m" style={{fontSize:".84rem"}}>
            <strong>Handoff —</strong> the display surfaces (cards, hero, feature list) can ship as block (a) immediately;
            the price-sort/filter requirement promotes Products to a structured content type (b) on the roadmap.
          </p>
          <a href="#/products" className="btn btn--primary mt-m">See the live Products controls <SAr/></a>
        </div>
      </section>
    </div>
  );
}

Object.assign(window, { SpecPage });
