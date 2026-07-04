// Table editing panel (tables track — user-prioritized). Edits TableData:
// headers, rows grid, add/remove row/column, stripes, border color.
// All edits flow through updateElement → gesture-scoped undo (W0-5).
import type { TableData } from '@/types/magazine';

interface TablePanelProps {
  data: TableData;
  onChange: (v: Partial<TableData>) => void;
}

export default function TablePanel({ data, onChange }: TablePanelProps) {
  const headers = data.headers?.length ? data.headers : ['Col 1', 'Col 2'];
  const rows = data.rows?.length ? data.rows : [['', '']];
  const cols = headers.length;

  const setHeader = (i: number, v: string) => {
    const next = [...headers];
    next[i] = v;
    onChange({ headers: next });
  };
  const setCell = (r: number, c: number, v: string) => {
    const next = rows.map((row) => [...row]);
    next[r][c] = v;
    onChange({ rows: next });
  };
  const addRow = () => onChange({ rows: [...rows, Array(cols).fill('')] });
  const removeRow = () => rows.length > 1 && onChange({ rows: rows.slice(0, -1) });
  const addCol = () => onChange({
    headers: [...headers, `Col ${cols + 1}`],
    rows: rows.map((r) => [...r, '']),
  });
  const removeCol = () => cols > 1 && onChange({
    headers: headers.slice(0, -1),
    rows: rows.map((r) => r.slice(0, -1)),
  });

  return (
    <div className="p-3 space-y-3 border-t border-base-300/20">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Table</h3>

      <div className="flex gap-1">
        <button className="btn btn-xs btn-ghost" onClick={addRow}>+ Row</button>
        <button className="btn btn-xs btn-ghost" onClick={removeRow} disabled={rows.length <= 1}>− Row</button>
        <button className="btn btn-xs btn-ghost" onClick={addCol}>+ Col</button>
        <button className="btn btn-xs btn-ghost" onClick={removeCol} disabled={cols <= 1}>− Col</button>
      </div>

      <div className="overflow-x-auto">
        <table className="border-collapse">
          <thead>
            <tr>
              {headers.map((h, i) => (
                <th key={i} className="p-0">
                  <input
                    className="input input-xs input-bordered w-24 font-semibold text-[10px] rounded-none"
                    value={h}
                    onChange={(e) => setHeader(i, e.target.value)}
                  />
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, ri) => (
              <tr key={ri}>
                {headers.map((_, ci) => (
                  <td key={ci} className="p-0">
                    <input
                      className="input input-xs input-bordered w-24 text-[10px] rounded-none"
                      value={row[ci] ?? ''}
                      onChange={(e) => setCell(ri, ci, e.target.value)}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex items-center gap-3">
        <label className="flex items-center gap-1.5 text-[11px] text-base-content/60 cursor-pointer">
          <input type="checkbox" className="checkbox checkbox-xs" checked={data.stripes !== false}
            onChange={(e) => onChange({ stripes: e.target.checked })} />
          Stripes
        </label>
        <label className="flex items-center gap-1.5 text-[11px] text-base-content/60">
          Border
          <input type="color" className="w-6 h-6 cursor-pointer border-0 bg-transparent"
            value={data.borderColor || '#e5e7eb'}
            onChange={(e) => onChange({ borderColor: e.target.value })} />
        </label>
      </div>
    </div>
  );
}
