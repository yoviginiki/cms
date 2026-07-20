import { Link, useParams } from 'react-router-dom';
import { Database, Search, LayoutGrid, ArrowRight } from 'lucide-react';

/** S6 — entry point for the deterministic scaffolding wizards. */
export default function WizardHub() {
  const { siteId = '' } = useParams();

  const cards = [
    {
      to: `/sites/${siteId}/wizards/database`,
      icon: Database,
      title: 'Database Wizard',
      body: 'Design one or more collections — fields, relations between them, and category-style hierarchies — in a single guided flow.',
    },
    {
      to: `/sites/${siteId}/wizards/search`,
      icon: Search,
      title: 'Search Wizard',
      body: 'Turn an existing collection into a searchable, filterable listing page with search box, facets and a results grid.',
    },
    {
      to: `/sites/${siteId}/wizards/app`,
      icon: LayoutGrid,
      title: 'App Wizard',
      body: 'The full build: collections, a detail template and index page for each, and a search page — one flow, ready to fill with data.',
    },
  ];

  return (
    <div className="max-w-3xl mx-auto">
      <h1 className="text-xl font-semibold mb-1">Wizards</h1>
      <p className="text-[13px] text-base-content/50 mb-6">Scaffold structured content and its pages without writing code. Everything created is a normal collection/page you can edit afterwards.</p>
      <div className="grid gap-4">
        {cards.map((c) => (
          <Link key={c.to} to={c.to} className="border border-base-300/40 rounded-box bg-base-100 p-5 flex items-start gap-4 hover:border-primary/40 transition-colors group">
            <div className="w-10 h-10 rounded-box bg-primary/10 text-primary flex items-center justify-center shrink-0"><c.icon size={20} /></div>
            <div className="flex-1">
              <h2 className="text-[15px] font-medium flex items-center gap-1">{c.title} <ArrowRight size={14} className="opacity-0 group-hover:opacity-60 transition-opacity" /></h2>
              <p className="text-[13px] text-base-content/55 mt-1">{c.body}</p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
