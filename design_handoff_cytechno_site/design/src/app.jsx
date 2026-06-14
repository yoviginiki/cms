/* ============================================================
   CYTECHNO — router + mount
   ============================================================ */
const { useState:aUS, useEffect:aUE } = React;

function parseHash(){
  let h = window.location.hash.replace(/^#\/?/, "");
  const parts = h.split("/").filter(Boolean);
  return { section: parts[0] || "", slug: parts[1] || "" };
}

function useRoute(){
  const [route,setRoute]=aUS(parseHash);
  aUE(()=>{
    const on=()=>{ setRoute(parseHash()); window.scrollTo({top:0,left:0,behavior:"instant"}); };
    window.addEventListener("hashchange",on);
    return ()=>window.removeEventListener("hashchange",on);
  },[]);
  return route;
}

function View({route}){
  const { section, slug } = route;
  switch(section){
    case "":          return <window.HomePage/>;
    case "about":     return <window.AboutPage/>;
    case "services":  return slug ? <window.ServiceSinglePage slug={slug}/> : <window.ServicesPage/>;
    case "portfolio": return slug ? <window.ProjectSinglePage slug={slug}/> : <window.PortfolioPage/>;
    case "blog":      return slug ? <window.PostSinglePage slug={slug}/> : <window.BlogPage/>;
    case "ideas":     return slug ? <window.IdeaSinglePage slug={slug}/> : <window.IdeasPage/>;
    case "products":  return slug ? <window.ProductSinglePage slug={slug}/> : <window.ProductsPage/>;
    case "contacts":  return <window.ContactsPage/>;
    case "spec":      return <window.SpecPage/>;
    default:          return <window.HomePage/>;
  }
}

function App(){
  const route = useRoute();
  return (
    <React.Fragment>
      <window.Header route={route}/>
      <main key={route.section+"/"+route.slug}>
        <View route={route}/>
      </main>
      <window.Footer/>
    </React.Fragment>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App/>);
