interface Column<T> {
  key: string;
  header: string;
  render?: (item: T) => React.ReactNode;
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  onRowClick?: (item: T) => void;
  emptyMessage?: string;
}

export function DataTable<T extends { id: string }>({
  columns,
  data,
  onRowClick,
  emptyMessage = 'No data found.',
}: DataTableProps<T>) {
  if (data.length === 0) {
    return (
      <div className="py-16 text-center text-[13px] text-base-content/30">{emptyMessage}</div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-box border border-base-300/40">
      <table className="table table-sm">
        <thead>
          <tr className="border-b border-base-300/40">
            {columns.map((col) => (
              <th key={col.key} className={`text-[11px] font-medium text-base-content/40 uppercase tracking-wider ${col.className ?? ''}`}>
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((item) => (
            <tr
              key={item.id}
              onClick={onRowClick ? () => onRowClick(item) : undefined}
              className={`border-b border-base-300/20 hover:bg-base-300/10 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
            >
              {columns.map((col) => (
                <td key={col.key} className={`text-[13px] text-base-content/80 ${col.className ?? ''}`}>
                  {col.render
                    ? col.render(item)
                    : (item as Record<string, unknown>)[col.key] as React.ReactNode}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
