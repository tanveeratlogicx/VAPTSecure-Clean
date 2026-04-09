// DataTable component for tabular data display

(function () {
  const { useState, useMemo, createElement: el } = wp.element || {};
  const components = wp.components || {};
  const { CheckboxControl, Button, Dashicon, Tooltip } = components;
  const { __ } = wp.i18n || {};

  const DataTable = ({
    columns,
    data,
    onSelect,
    selectedRows = [],
    selectable = false,
    sortable = false,
    onSort = null,
    sortColumn = null,
    sortDirection = 'asc',
    emptyMessage = __('No data available', 'vaptsecure'),
    rowActions = null,
    compact = false
  }) => {
    const [localSelected, setLocalSelected] = useState([]);

    const handleSelectAll = (checked) => {
      if (checked) {
        const allIds = data.map(item => item.id || item.key);
        setLocalSelected(allIds);
        if (onSelect) onSelect(allIds);
      } else {
        setLocalSelected([]);
        if (onSelect) onSelect([]);
      }
    };

    const handleSelectRow = (id, checked) => {
      let newSelected;
      if (checked) {
        newSelected = [...localSelected, id];
      } else {
        newSelected = localSelected.filter(itemId => itemId !== id);
      }
      setLocalSelected(newSelected);
      if (onSelect) onSelect(newSelected);
    };

    const handleSort = (columnId) => {
      if (onSort) {
        const newDirection = sortColumn === columnId && sortDirection === 'asc' ? 'desc' : 'asc';
        onSort(columnId, newDirection);
      }
    };

    const allSelected = useMemo(() => {
      if (!data.length) return false;
      const allIds = data.map(item => item.id || item.key);
      return allIds.every(id => localSelected.includes(id));
    }, [data, localSelected]);

    const someSelected = useMemo(() => {
      return localSelected.length > 0 && !allSelected;
    }, [localSelected, allSelected]);

    return el('div', { className: 'vaptsecure-data-table' },
      // Table
      el('table', { 
        style: { 
          width: '100%', 
          borderCollapse: 'collapse',
          fontSize: compact ? '13px' : '14px'
        } 
      },
        // Header
        el('thead', {},
          el('tr', { style: { backgroundColor: '#f6f7f7', borderBottom: '2px solid #c3c4c7' } },
            selectable && el('th', { 
              style: { 
                padding: compact ? '8px' : '12px', 
                textAlign: 'center',
                width: '40px'
              } 
            },
              el(CheckboxControl, {
                checked: allSelected,
                indeterminate: someSelected,
                onChange: handleSelectAll,
                __nextHasNoMarginBottom: true
              })
            ),
            columns.map(col => el('th', {
              key: col.id,
              style: { 
                padding: compact ? '8px' : '12px', 
                textAlign: 'left',
                fontWeight: '600',
                cursor: sortable ? 'pointer' : 'default',
                whiteSpace: 'nowrap'
              },
              onClick: sortable ? () => handleSort(col.id) : null
            },
              el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } },
                col.header,
                sortable && sortColumn === col.id && el(Dashicon, {
                  icon: sortDirection === 'asc' ? 'arrow-up' : 'arrow-down',
                  size: 12,
                  style: { opacity: 0.6 }
                })
              )
            )),
            rowActions && el('th', { 
              style: { 
                padding: compact ? '8px' : '12px', 
                textAlign: 'center',
                width: '100px'
              } 
            }, __('Actions', 'vaptsecure'))
          )
        ),

        // Body
        el('tbody', {},
          data.length === 0 ? el('tr', {},
            el('td', { 
              colSpan: columns.length + (selectable ? 1 : 0) + (rowActions ? 1 : 0),
              style: { 
                padding: '40px 20px', 
                textAlign: 'center',
                color: '#757575'
              } 
            }, emptyMessage)
          ) : data.map((row, rowIndex) => {
            const rowId = row.id || row.key || rowIndex;
            const isSelected = localSelected.includes(rowId);

            return el('tr', {
              key: rowId,
              style: { 
                backgroundColor: rowIndex % 2 === 0 ? '#fff' : '#f9f9f9',
                borderBottom: '1px solid #e0e0e0',
                ':hover': { backgroundColor: '#f0f6ff' }
              }
            },
              selectable && el('td', { 
                style: { 
                  padding: compact ? '8px' : '12px', 
                  textAlign: 'center',
                  verticalAlign: 'middle'
                } 
              },
                el(CheckboxControl, {
                  checked: isSelected,
                  onChange: (checked) => handleSelectRow(rowId, checked),
                  __nextHasNoMarginBottom: true
                })
              ),
              columns.map(col => {
                const cellValue = typeof col.accessor === 'function' 
                  ? col.accessor(row) 
                  : row[col.accessor];
                
                const cellContent = col.cell ? col.cell({ value: cellValue, row }) : cellValue;

                return el('td', {
                  key: col.id,
                  style: { 
                    padding: compact ? '8px' : '12px', 
                    verticalAlign: 'middle',
                    ...(col.style || {})
                  }
                }, cellContent);
              }),
              rowActions && el('td', { 
                style: { 
                  padding: compact ? '8px' : '12px', 
                  textAlign: 'center',
                  verticalAlign: 'middle'
                } 
              }, rowActions({ row }))
            );
          })
        )
      ),

      // Footer with selection info
      selectable && localSelected.length > 0 && el('div', {
        style: {
          marginTop: '10px',
          padding: '10px',
          backgroundColor: '#f0f6ff',
          border: '1px solid #c3c4c7',
          borderRadius: '4px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between'
        }
      },
        el('span', { style: { fontSize: '13px', color: '#1e1e1e' } },
          sprintf(__('%d item(s) selected', 'vaptsecure'), localSelected.length)
        ),
        el(Button, {
          isSmall: true,
          isLink: true,
          onClick: () => {
            setLocalSelected([]);
            if (onSelect) onSelect([]);
          }
        }, __('Clear selection', 'vaptsecure'))
      )
    );
  };

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  if (!window.vaptAdmin.shared) window.vaptAdmin.shared = {};
  window.vaptAdmin.shared.DataTable = DataTable;
})();