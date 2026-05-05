import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { Loader2, ZoomIn, ZoomOut, Maximize2 } from 'lucide-react';
import { api } from '@/lib/api';

interface GraphNode {
  id: string;
  type: string;
  label: string;
  slug?: string;
  location?: string;
  x?: number;
  y?: number;
}

interface GraphEdge {
  from: string;
  to: string;
  relation: string;
}

interface GraphData {
  nodes: GraphNode[];
  edges: GraphEdge[];
  stats: Record<string, number>;
}

const TYPE_CONFIG: Record<string, { color: string; bg: string; radius: number; emoji: string }> = {
  page:     { color: '#2563eb', bg: '#dbeafe', radius: 24, emoji: '📄' },
  post:     { color: '#16a34a', bg: '#dcfce7', radius: 18, emoji: '📝' },
  category: { color: '#9333ea', bg: '#f3e8ff', radius: 20, emoji: '📁' },
  tag:      { color: '#ea580c', bg: '#ffedd5', radius: 16, emoji: '🏷' },
  menu:     { color: '#0891b2', bg: '#cffafe', radius: 22, emoji: '☰' },
  archive:  { color: '#4f46e5', bg: '#e0e7ff', radius: 20, emoji: '📚' },
  feed:     { color: '#ca8a04', bg: '#fef9c3', radius: 16, emoji: '📡' },
};

const EDGE_COLORS: Record<string, string> = {
  belongs_to: '#9333ea',
  tagged: '#ea580c',
  links_to: '#0891b2',
  renders_on: '#d1d5db',
  listed_in: '#16a34a',
};

export default function ContentGraph() {
  const { siteId = '' } = useParams();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [isPanning, setIsPanning] = useState(false);
  const [hoveredNode, setHoveredNode] = useState<GraphNode | null>(null);
  const [selectedNode, setSelectedNode] = useState<GraphNode | null>(null);
  const [filterType, setFilterType] = useState<string>('all');
  const [showEdgeType, setShowEdgeType] = useState<string>('all');
  const [layoutNodes, setLayoutNodes] = useState<GraphNode[]>([]);

  const { data, isLoading } = useQuery<GraphData>({
    queryKey: ['content-graph', siteId],
    queryFn: () => api.get(`/sites/${siteId}/dependency-graph`).then(r => r.data.data),
  });

  // Force-directed layout
  useEffect(() => {
    if (!data) return;

    const nodes = data.nodes.map((n) => {
      // Group by type in clusters
      const typeIndex = Object.keys(TYPE_CONFIG).indexOf(n.type);
      const countOfType = data.nodes.filter(nn => nn.type === n.type).length;
      const indexInType = data.nodes.filter(nn => nn.type === n.type).indexOf(n);
      const angle = (indexInType / Math.max(countOfType, 1)) * Math.PI * 2;
      const clusterRadius = Math.sqrt(countOfType) * 40;
      const clusterX = 400 + Math.cos(typeIndex * Math.PI * 2 / 7) * 250;
      const clusterY = 350 + Math.sin(typeIndex * Math.PI * 2 / 7) * 200;

      return {
        ...n,
        x: clusterX + Math.cos(angle) * clusterRadius,
        y: clusterY + Math.sin(angle) * clusterRadius,
      };
    });

    // Simple force simulation (a few iterations)
    const edges = data.edges;
    for (let iter = 0; iter < 80; iter++) {
      // Repulsion between all nodes
      for (let i = 0; i < nodes.length; i++) {
        for (let j = i + 1; j < nodes.length; j++) {
          const dx = nodes[j].x! - nodes[i].x!;
          const dy = nodes[j].y! - nodes[i].y!;
          const dist = Math.max(Math.sqrt(dx * dx + dy * dy), 1);
          const force = 800 / (dist * dist);
          const fx = (dx / dist) * force;
          const fy = (dy / dist) * force;
          nodes[i].x! -= fx;
          nodes[i].y! -= fy;
          nodes[j].x! += fx;
          nodes[j].y! += fy;
        }
      }

      // Attraction along edges (skip renders_on — too many)
      for (const edge of edges) {
        if (edge.relation === 'renders_on') continue;
        const a = nodes.find(n => n.id === edge.from);
        const b = nodes.find(n => n.id === edge.to);
        if (!a || !b) continue;
        const dx = b.x! - a.x!;
        const dy = b.y! - a.y!;
        const dist = Math.max(Math.sqrt(dx * dx + dy * dy), 1);
        const force = (dist - 100) * 0.01;
        const fx = (dx / dist) * force;
        const fy = (dy / dist) * force;
        a.x! += fx; a.y! += fy;
        b.x! -= fx; b.y! -= fy;
      }

      // Center gravity
      for (const n of nodes) {
        n.x! += (400 - n.x!) * 0.01;
        n.y! += (350 - n.y!) * 0.01;
      }
    }

    setLayoutNodes(nodes);
  }, [data]);

  // Canvas rendering
  useEffect(() => {
    if (!canvasRef.current || !data || layoutNodes.length === 0) return;

    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const container = containerRef.current;
    if (container) {
      canvas.width = container.clientWidth;
      canvas.height = container.clientHeight;
    }

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.save();
    ctx.translate(pan.x + canvas.width / 2 - 400 * zoom, pan.y + canvas.height / 2 - 350 * zoom);
    ctx.scale(zoom, zoom);

    // Draw edges
    for (const edge of data.edges) {
      if (showEdgeType !== 'all' && edge.relation !== showEdgeType) continue;
      if (edge.relation === 'renders_on' && showEdgeType !== 'renders_on') continue; // hide menu→page edges by default

      const from = layoutNodes.find(n => n.id === edge.from);
      const to = layoutNodes.find(n => n.id === edge.to);
      if (!from || !to) continue;

      // Filter
      if (filterType !== 'all') {
        const fromType = from.type;
        const toType = to.type;
        if (fromType !== filterType && toType !== filterType) continue;
      }

      ctx.beginPath();
      ctx.moveTo(from.x!, from.y!);
      ctx.lineTo(to.x!, to.y!);
      ctx.strokeStyle = (EDGE_COLORS[edge.relation] || '#d1d5db') + '40';
      ctx.lineWidth = edge.relation === 'renders_on' ? 0.5 : 1;
      ctx.stroke();
    }

    // Draw nodes
    for (const node of layoutNodes) {
      if (filterType !== 'all' && node.type !== filterType) continue;

      const config = TYPE_CONFIG[node.type] || TYPE_CONFIG.page;
      const isHovered = hoveredNode?.id === node.id;
      const isSelected = selectedNode?.id === node.id;
      const r = config.radius * (isHovered || isSelected ? 1.3 : 1);

      // Shadow
      if (isHovered || isSelected) {
        ctx.shadowColor = config.color + '40';
        ctx.shadowBlur = 12;
      }

      // Circle
      ctx.beginPath();
      ctx.arc(node.x!, node.y!, r, 0, Math.PI * 2);
      ctx.fillStyle = isSelected ? config.color : config.bg;
      ctx.fill();
      ctx.strokeStyle = config.color;
      ctx.lineWidth = isSelected ? 3 : 1.5;
      ctx.stroke();

      ctx.shadowColor = 'transparent';
      ctx.shadowBlur = 0;

      // Label
      ctx.fillStyle = isSelected ? '#fff' : config.color;
      ctx.font = `${isHovered ? 'bold' : 'normal'} ${Math.max(9, 11 / zoom)}px system-ui`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';

      const label = node.label.length > 14 ? node.label.slice(0, 12) + '…' : node.label;
      ctx.fillText(label, node.x!, node.y! + (r > 20 ? 0 : 1));
    }

    ctx.restore();
  }, [layoutNodes, data, zoom, pan, hoveredNode, selectedNode, filterType, showEdgeType]);

  // Mouse interaction
  const findNodeAt = useCallback((clientX: number, clientY: number): GraphNode | null => {
    if (!canvasRef.current || layoutNodes.length === 0) return null;
    const rect = canvasRef.current.getBoundingClientRect();
    const mx = (clientX - rect.left - pan.x - canvasRef.current.width / 2 + 400 * zoom) / zoom;
    const my = (clientY - rect.top - pan.y - canvasRef.current.height / 2 + 350 * zoom) / zoom;

    for (let i = layoutNodes.length - 1; i >= 0; i--) {
      const n = layoutNodes[i];
      if (filterType !== 'all' && n.type !== filterType) continue;
      const r = (TYPE_CONFIG[n.type]?.radius || 20);
      const dx = n.x! - mx;
      const dy = n.y! - my;
      if (dx * dx + dy * dy < r * r * 1.5) return n;
    }
    return null;
  }, [layoutNodes, zoom, pan, filterType]);

  const handleMouseMove = useCallback((e: React.MouseEvent) => {
    if (isPanning) {
      setPan(p => ({ x: p.x + e.movementX, y: p.y + e.movementY }));
      return;
    }
    const node = findNodeAt(e.clientX, e.clientY);
    setHoveredNode(node);
    if (canvasRef.current) canvasRef.current.style.cursor = node ? 'pointer' : 'grab';
  }, [isPanning, findNodeAt]);

  const handleClick = useCallback((e: React.MouseEvent) => {
    const node = findNodeAt(e.clientX, e.clientY);
    setSelectedNode(node === selectedNode ? null : node);
  }, [findNodeAt, selectedNode]);

  // Selected node connections
  const selectedConnections = selectedNode && data ? {
    incoming: data.edges.filter(e => e.to === selectedNode.id).map(e => ({
      ...e,
      node: data.nodes.find(n => n.id === e.from),
    })),
    outgoing: data.edges.filter(e => e.from === selectedNode.id).map(e => ({
      ...e,
      node: data.nodes.find(n => n.id === e.to),
    })),
  } : null;

  if (isLoading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (!data) return null;

  return (
    <div className="flex flex-col h-[calc(100vh-60px)]">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-2 bg-white border-b shrink-0">
        <div>
          <h1 className="text-lg font-bold text-gray-900">Content Graph</h1>
          <p className="text-xs text-gray-400">{data.stats.pages} pages · {data.stats.posts} posts · {data.stats.categories} categories · {data.stats.tags} tags · {data.edges.length} connections</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => setZoom(z => Math.min(z * 1.3, 3))} className="p-1.5 hover:bg-gray-100 rounded"><ZoomIn size={16} /></button>
          <button onClick={() => setZoom(z => Math.max(z / 1.3, 0.3))} className="p-1.5 hover:bg-gray-100 rounded"><ZoomOut size={16} /></button>
          <button onClick={() => { setZoom(1); setPan({ x: 0, y: 0 }); }} className="p-1.5 hover:bg-gray-100 rounded"><Maximize2 size={16} /></button>
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Canvas */}
        <div ref={containerRef} className="flex-1 bg-gray-50 relative overflow-hidden"
          onWheel={e => { e.preventDefault(); setZoom(z => Math.max(0.2, Math.min(4, z * (e.deltaY > 0 ? 0.9 : 1.1)))); }}>
          <canvas ref={canvasRef} className="w-full h-full"
            onMouseMove={handleMouseMove}
            onClick={handleClick}
            onMouseDown={e => { if (!findNodeAt(e.clientX, e.clientY)) setIsPanning(true); }}
            onMouseUp={() => setIsPanning(false)}
            onMouseLeave={() => { setIsPanning(false); setHoveredNode(null); }}
          />

          {/* Filter bar overlay */}
          <div className="absolute top-3 left-3 flex flex-wrap gap-1">
            <button onClick={() => setFilterType('all')}
              className={`px-2 py-1 text-xs rounded-full ${filterType === 'all' ? 'bg-gray-900 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50'}`}>
              All
            </button>
            {Object.entries(TYPE_CONFIG).map(([type, config]) => (
              <button key={type} onClick={() => setFilterType(filterType === type ? 'all' : type)}
                className={`px-2 py-1 text-xs rounded-full flex items-center gap-1 ${
                  filterType === type ? 'text-white' : 'bg-white border text-gray-600 hover:bg-gray-50'
                }`}
                style={filterType === type ? { backgroundColor: config.color } : undefined}>
                <span>{config.emoji}</span>
                <span className="capitalize">{type}s</span>
                <span className="opacity-60">({data.stats[type + 's'] || data.stats[type] || 0})</span>
              </button>
            ))}
          </div>

          {/* Legend */}
          <div className="absolute bottom-3 left-3 bg-white/90 backdrop-blur rounded-lg border p-2 text-[10px]">
            <div className="flex flex-wrap gap-x-3 gap-y-1">
              {Object.entries(EDGE_COLORS).filter(([k]) => k !== 'renders_on').map(([rel, color]) => (
                <button key={rel} onClick={() => setShowEdgeType(showEdgeType === rel ? 'all' : rel)}
                  className={`flex items-center gap-1 ${showEdgeType === rel ? 'font-bold' : ''}`}>
                  <span className="w-3 h-0.5 inline-block rounded" style={{ backgroundColor: color }} />
                  <span className="text-gray-500">{rel.replace('_', ' ')}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Hover tooltip */}
          {hoveredNode && !selectedNode && (
            <div className="absolute top-3 right-3 bg-white rounded-lg border shadow-lg p-3 w-56">
              <div className="flex items-center gap-2 mb-1">
                <span>{TYPE_CONFIG[hoveredNode.type]?.emoji}</span>
                <span className="font-medium text-sm">{hoveredNode.label}</span>
              </div>
              <p className="text-xs text-gray-400 capitalize">{hoveredNode.type}</p>
              {hoveredNode.slug && <p className="text-xs text-gray-400 font-mono">/{hoveredNode.slug}</p>}
            </div>
          )}
        </div>

        {/* Right panel — selected node details */}
        {selectedNode && selectedConnections && (
          <div className="w-72 bg-white border-l overflow-y-auto shrink-0 p-4">
            <div className="flex items-center gap-2 mb-3">
              <span className="text-lg">{TYPE_CONFIG[selectedNode.type]?.emoji}</span>
              <div>
                <h3 className="font-semibold text-gray-900">{selectedNode.label}</h3>
                <p className="text-xs text-gray-400 capitalize">{selectedNode.type}</p>
              </div>
              <button onClick={() => setSelectedNode(null)} className="ml-auto text-gray-400 hover:text-gray-600 text-xs">✕</button>
            </div>

            {selectedNode.slug && (
              <p className="text-xs font-mono text-gray-400 mb-3 bg-gray-50 rounded px-2 py-1">/{selectedNode.slug}</p>
            )}

            {/* Incoming connections */}
            {selectedConnections.incoming.length > 0 && (
              <div className="mb-4">
                <h4 className="text-xs font-medium text-gray-500 uppercase mb-2">← Depends on ({selectedConnections.incoming.length})</h4>
                <div className="space-y-1">
                  {selectedConnections.incoming.slice(0, 20).map((c, i) => (
                    <button key={i} onClick={() => { const n = layoutNodes.find(ln => ln.id === c.node?.id); if (n) setSelectedNode(n); }}
                      className="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 text-xs">
                      <span>{TYPE_CONFIG[c.node?.type || 'page']?.emoji}</span>
                      <span className="truncate text-gray-700">{c.node?.label}</span>
                      <span className="ml-auto text-gray-300 text-[10px]">{c.relation.replace('_', ' ')}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Outgoing connections */}
            {selectedConnections.outgoing.length > 0 && (
              <div>
                <h4 className="text-xs font-medium text-gray-500 uppercase mb-2">→ Affects ({selectedConnections.outgoing.length})</h4>
                <div className="space-y-1">
                  {selectedConnections.outgoing.slice(0, 20).map((c, i) => (
                    <button key={i} onClick={() => { const n = layoutNodes.find(ln => ln.id === c.node?.id); if (n) setSelectedNode(n); }}
                      className="w-full text-left flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 text-xs">
                      <span>{TYPE_CONFIG[c.node?.type || 'page']?.emoji}</span>
                      <span className="truncate text-gray-700">{c.node?.label}</span>
                      <span className="ml-auto text-gray-300 text-[10px]">{c.relation.replace('_', ' ')}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            <div className="mt-4 p-2 bg-blue-50 border border-blue-100 rounded text-[10px] text-blue-700">
              When this {selectedNode.type} changes, {selectedConnections.outgoing.length} items need rebuilding.
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
