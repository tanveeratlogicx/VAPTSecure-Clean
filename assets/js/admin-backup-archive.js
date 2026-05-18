// Superadmin Backup Archive UI
// React-based interface for managing schema backups

var vaptBackupLog = {
  log: (...args) => window.VAPT_DEBUG && console.log('[VAPT Backup]', ...args),
  error: (...args) => console.error('[VAPT Backup]', ...args)
};

(function () {
  vaptBackupLog.log('Script loaded');

  if (typeof wp === 'undefined') {
    vaptBackupLog.error('"wp" global is missing!');
    return;
  }

  const { render, useState, useEffect, useCallback, createElement: el } = wp.element || {};
  if (!render || !el) {
    vaptBackupLog.error('wp.element not loaded properly');
    return;
  }

  const components = wp.components || {};
  const {
    TabPanel, Button, Spinner, Notice, Modal, CheckboxControl, TextControl, Dropdown, Icon
  } = {
    TabPanel: components.TabPanel || components.__experimentalTabPanel,
    Button: components.Button,
    Spinner: components.Spinner,
    Notice: components.Notice,
    Modal: components.Modal,
    CheckboxControl: components.CheckboxControl,
    TextControl: components.TextControl,
    Dropdown: components.Dropdown,
    Icon: components.Icon || wp.components.Dashicon
  };

  if (!TabPanel || !Button || !Spinner) {
    vaptBackupLog.error('Missing wp.components:', { TabPanel: !!TabPanel, Button: !!Button, Spinner: !!Spinner });
    return;
  }

  const apiFetch = wp.apiFetch;
  const { __, sprintf } = wp.i18n || { __: (t) => t, sprintf: (t, ...args) => t };
  const settings = window.vaptSecureSettings || {};

  vaptBackupLog.log('Components loaded, initializing UI');

  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString();
  };

  const BackupArchivePage = () => {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    
    const [activeBackups, setActiveBackups] = useState([]);
    const [activeTotal, setActiveTotal] = useState(0);
    const [activePage, setActivePage] = useState(1);
    const [activeSelected, setActiveSelected] = useState([]);
    
    const [archivedBackups, setArchivedBackups] = useState([]);
    const [archivedTotal, setArchivedTotal] = useState(0);
    const [archivedPage, setArchivedPage] = useState(1);
    const [archivedSelected, setArchivedSelected] = useState([]);
    
    const [missingCount, setMissingCount] = useState(0);
    const [syncing, setSyncing] = useState(false);
    const [resyncing, setResyncing] = useState(false);
    
    const [modal, setModal] = useState(null);
    const [processing, setProcessing] = useState(false);

    // Search, Sort, Filter state
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategories, setSelectedCategories] = useState([]);
    const [filterStatus, setFilterStatus] = useState('all');
    const [sortBy, setSortBy] = useState('backed_up_at');
    const [sortOrder, setSortOrder] = useState('desc');

    const perPage = 20;

    const fetchActiveBackups = useCallback((page = 1) => {
      apiFetch({ path: `/vaptsecure/v1/backups/active?page=${page}&per_page=1000` })
        .then(data => {
          setActiveBackups(data.items || []);
          setActiveTotal(data.total || 0);
          setActivePage(page);
          setActiveSelected([]);
          setLoading(false);
        })
        .catch(err => {
          setError(err.message || 'Failed to load active backups');
          setLoading(false);
        });
    }, []);

    const fetchArchivedBackups = useCallback((page = 1) => {
      apiFetch({ path: `/vaptsecure/v1/backups/archived?page=${page}&per_page=1000` })
        .then(data => {
          setArchivedBackups(data.items || []);
          setArchivedTotal(data.total || 0);
          setArchivedPage(page);
          setArchivedSelected([]);
          setLoading(false);
        })
        .catch(err => {
          setError(err.message || 'Failed to load archived backups');
          setLoading(false);
        });
    }, []);

    const fetchMissingCount = useCallback(() => {
      apiFetch({ path: '/vaptsecure/v1/backups/missing-count' })
        .then(data => setMissingCount(data.count || 0))
        .catch(() => setMissingCount(0));
    }, []);

    useEffect(() => {
      vaptBackupLog.log('Component mounted, fetching data');
      fetchActiveBackups(1);
      fetchArchivedBackups(1);
      fetchMissingCount();
    }, []);

    const syncExisting = () => {
      setSyncing(true);
      apiFetch({ path: '/vaptsecure/v1/backups/sync-existing', method: 'POST' })
        .then(data => {
          setSyncing(false);
          setMissingCount(0);
          fetchActiveBackups(activePage);
        })
        .catch(err => {
          setSyncing(false);
          setError(err.message || 'Sync failed');
        });
    };

    const resyncTitles = () => {
      setResyncing(true);
      apiFetch({ path: '/vaptsecure/v1/backups/resync-titles', method: 'POST' })
        .then(data => {
          setResyncing(false);
          fetchActiveBackups(activePage);
          fetchArchivedBackups(archivedPage);
        })
        .catch(err => {
          setResyncing(false);
          setError(err.message || 'Resync failed');
        });
    };

    const toggleSelect = (key, fromArchive) => {
      if (fromArchive) {
        setArchivedSelected(prev => 
          prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]
        );
      } else {
        setActiveSelected(prev => 
          prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]
        );
      }
    };

    const toggleSelectAll = (items, fromArchive) => {
      const keys = items.map(i => i.feature_key);
      if (fromArchive) {
        setArchivedSelected(prev => prev.length === items.length ? [] : keys);
      } else {
        setActiveSelected(prev => prev.length === items.length ? [] : keys);
      }
    };

    const openModal = (type, fromArchive) => {
      const items = fromArchive ? 
        archivedBackups.filter(b => archivedSelected.includes(b.feature_key)) :
        activeBackups.filter(b => activeSelected.includes(b.feature_key));
      setModal({ type, items, fromArchive });
    };

    const executeAction = () => {
      if (!modal || modal.items.length === 0) return;
      setProcessing(true);

      const keys = modal.items.map(i => i.feature_key);
      let promise;

      if (modal.type === 'purge') {
        const path = modal.fromArchive ? '/vaptsecure/v1/backups/purge-archive' : '/vaptsecure/v1/backups/purge';
        promise = apiFetch({ path, method: 'DELETE', data: { feature_keys: keys } });
      } else if (modal.type === 'restore') {
        const promises = keys.map(key => 
          apiFetch({ path: `/vaptsecure/v1/backups/${key}/restore`, method: 'POST' })
        );
        promise = Promise.all(promises);
      } else if (modal.type === 'archive') {
        const promises = keys.map(key => 
          apiFetch({ path: `/vaptsecure/v1/backups/${key}/archive`, method: 'POST' })
        );
        promise = Promise.all(promises);
      }

      promise.then(() => {
        setProcessing(false);
        setModal(null);
        if (modal.fromArchive) {
          fetchArchivedBackups(archivedPage);
        } else {
          fetchActiveBackups(activePage);
        }
      }).catch(err => {
        setProcessing(false);
        setError(err.message || 'Action failed');
      });
    };

    // Get unique categories from all backups
    const getAllCategories = (backups) => {
      const cats = new Set();
      backups.forEach(b => {
        if (b.feature_category) cats.add(b.feature_category);
      });
      return Array.from(cats).sort();
    };

    // Process backups: filter, search, sort
    const processBackups = (backups, fromArchive) => {
      let processed = [...backups];

      // Category filter
      if (selectedCategories.length > 0) {
        processed = processed.filter(b => selectedCategories.includes(b.feature_category));
      }

      // Status filter
      if (filterStatus !== 'all') {
        const statusField = fromArchive ? 'status_at_archive' : 'status_at_backup';
        processed = processed.filter(b => {
          const s = (b[statusField] || '').toLowerCase();
          if (filterStatus === 'develop') return s === 'develop';
          if (filterStatus === 'release') return s === 'release';
          if (filterStatus === 'draft') return s === 'draft';
          if (filterStatus === 'test') return s === 'test';
          return true;
        });
      }

      // Search
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        processed = processed.filter(b => 
          (b.feature_key || '').toLowerCase().includes(q) ||
          (b.feature_title || '').toLowerCase().includes(q) ||
          (b.feature_category || '').toLowerCase().includes(q)
        );
      }

      // Sort
      const dateField = fromArchive ? 'archived_at' : 'backed_up_at';
      const statusField = fromArchive ? 'status_at_archive' : 'status_at_backup';
      
      processed.sort((a, b) => {
        let comparison = 0;
        
        if (sortBy === 'feature_key') {
          comparison = (a.feature_key || '').localeCompare(b.feature_key || '');
        } else if (sortBy === 'feature_title') {
          comparison = (a.feature_title || '').localeCompare(b.feature_title || '');
        } else if (sortBy === 'feature_category') {
          comparison = (a.feature_category || '').localeCompare(b.feature_category || '');
        } else if (sortBy === 'status') {
          comparison = (a[statusField] || '').localeCompare(b[statusField] || '');
        } else if (sortBy === 'date') {
          const dateA = new Date(a[dateField] || 0);
          const dateB = new Date(b[dateField] || 0);
          comparison = dateA - dateB;
        }

        return sortOrder === 'asc' ? comparison : -comparison;
      });

      return processed;
    };

    const resetFilters = () => {
      setSearchQuery('');
      setSelectedCategories([]);
      setFilterStatus('all');
      setSortBy('backed_up_at');
      setSortOrder('desc');
    };

    const renderSortIcon = (column) => {
      if (sortBy !== column) return null;
      return el(Icon, { 
        icon: sortOrder === 'asc' ? 'arrow-up-alt2' : 'arrow-down-alt2', 
        size: 14,
        style: { marginLeft: '4px', verticalAlign: 'middle' }
      });
    };

    const renderRows = (items, selected, fromArchive) => {
      if (!items || items.length === 0) {
        return el('tr', null, 
          el('td', { colSpan: 7, style: { textAlign: 'center', padding: '40px', color: '#64748b' } }, 
            __('No records found.', 'vaptsecure')
          )
        );
      }

      return items.map(item => {
        const key = item.feature_key;
        const isSelected = selected.includes(key);
        const date = fromArchive ? item.archived_at : item.backed_up_at;
        const status = fromArchive ? item.status_at_archive : item.status_at_backup;

        return el('tr', { key, className: isSelected ? 'vapt-selected-row' : '' }, [
          el('td', { style: { width: '40px' } }, 
            el(CheckboxControl, { 
              checked: isSelected, 
              onChange: () => toggleSelect(key, fromArchive),
              __nextHasNoMarginBottom: true
            })
          ),
          el('td', null, el('code', null, key)),
          el('td', null, item.feature_category || '-'),
          el('td', { style: { whiteSpace: 'nowrap', maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis' } }, item.feature_title || '-'),
          el('td', null, status || '-'),
          el('td', null, formatDate(date)),
          el('td', { style: { width: '180px' } }, 
            fromArchive ? 
              el(Button, { 
                isPrimary: true, 
                isSmall: true,
                onClick: () => setModal({ type: 'restore', items: [item], fromArchive: true })
              }, __('Restore', 'vaptsecure')) :
              el('div', { style: { display: 'flex', gap: '5px' } }, [
                el(Button, { 
                  isSecondary: true, 
                  isSmall: true,
                  onClick: () => setModal({ type: 'archive', items: [item], fromArchive: false })
                }, __('Archive', 'vaptsecure')),
                el(Button, { 
                  isDestructive: true, 
                  isSmall: true,
                  onClick: () => setModal({ type: 'purge', items: [item], fromArchive: false })
                }, __('Purge', 'vaptsecure'))
              ])
          )
        ]);
      });
    };

    const renderPagination = (total, currentPage, setPage, filteredItems) => {
      const totalPages = Math.ceil(filteredItems.length / perPage);
      if (totalPages <= 1) return null;

      const startIdx = (currentPage - 1) * perPage;
      const endIdx = Math.min(currentPage * perPage, filteredItems.length);
      const pageItems = filteredItems.slice(startIdx, endIdx);

      return el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '15px', padding: '10px 0' } }, [
        el('span', { style: { fontSize: '13px', color: '#64748b' } }, 
          sprintf(__('Showing %d-%d of %d', 'vaptsecure'), startIdx + 1, endIdx, filteredItems.length)
        ),
        el('div', { style: { display: 'flex', gap: '5px' } }, [
          el(Button, { 
            disabled: currentPage === 1, 
            isSecondary: true, 
            isSmall: true,
            onClick: () => setPage(currentPage - 1)
          }, __('Previous', 'vaptsecure')),
          el(Button, { 
            disabled: currentPage === totalPages, 
            isSecondary: true, 
            isSmall: true,
            onClick: () => setPage(currentPage + 1)
          }, __('Next', 'vaptsecure'))
        ])
      ]);
    };

    const renderFilterBar = (backups, fromArchive) => {
      const allCategories = getAllCategories(backups);
      const processed = processBackups(backups, fromArchive);
      const hasActiveFilters = searchQuery || selectedCategories.length > 0 || filterStatus !== 'all';

      return el('div', { style: { marginBottom: '15px' } }, [
        // Summary row
        el('div', { style: { display: 'flex', gap: '15px', padding: '6px 15px', background: '#fff', border: '1px solid #dcdcde', borderRadius: '4px', marginBottom: '10px', alignItems: 'center', fontSize: '11px', color: '#333' } }, [
          el('span', { style: { fontWeight: '700', textTransform: 'uppercase', fontSize: '10px', color: '#666' } }, __('Summary:', 'vaptsecure')),
          el('span', { style: { fontWeight: '600', color: '#2271b1' } },
            processed.length === backups.length
              ? sprintf(__('Total: %d', 'vaptsecure'), processed.length)
              : sprintf(__('Filtered: %d of %d', 'vaptsecure'), processed.length, backups.length)
          ),
          hasActiveFilters && el(Button, {
            isLink: true,
            isSmall: true,
            onClick: resetFilters,
            style: { marginLeft: 'auto', fontSize: '10px', fontWeight: '600', textTransform: 'uppercase' }
          }, __('Reset All Filters', 'vaptsecure'))
        ]),
        // Filter row
        el('div', { style: { display: 'flex', gap: '8px', flexWrap: 'nowrap', alignItems: 'stretch' } }, [
          // Search
          el('div', { style: { flex: '1 1 180px', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666' } }, __('Search', 'vaptsecure')),
            el(TextControl, {
              value: searchQuery,
              onChange: setSearchQuery,
              placeholder: __('Search key, title, category...', 'vaptsecure'),
              hideLabelFromVision: true,
              style: { margin: 0, height: '28px', minHeight: '28px', fontSize: '12px' }
            })
          ]),
          // Category filter
          el('div', { style: { flex: '0 0 auto', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center', minWidth: '150px' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666' } }, __('Filter by Category', 'vaptsecure')),
            el(Dropdown, {
              renderToggle: ({ isOpen, onToggle }) => el(Button, {
                isSecondary: true,
                onClick: onToggle,
                'aria-expanded': isOpen,
                icon: 'filter',
                style: { height: '28px', minHeight: '28px', width: '100%', justifyContent: 'flex-start', gap: '6px', borderColor: '#2271b1', color: '#2271b1', background: '#fff', fontSize: '11px', padding: '0 8px' }
              }, selectedCategories.length === 0 ? __('All Categories', 'vaptsecure') : sprintf(__('%d Selected', 'vaptsecure'), selectedCategories.length)),
              renderContent: () => el('div', { style: { padding: '15px', minWidth: '200px', maxHeight: '250px', overflowY: 'auto' } }, [
                el(CheckboxControl, {
                  label: __('All Categories', 'vaptsecure'),
                  checked: selectedCategories.length === 0,
                  onChange: () => setSelectedCategories([])
                }),
                el('hr', { style: { margin: '10px 0' } }),
                ...allCategories.map(cat => el(CheckboxControl, {
                  key: cat,
                  label: cat,
                  checked: selectedCategories.includes(cat),
                  onChange: (isChecked) => {
                    if (isChecked) setSelectedCategories([...selectedCategories, cat]);
                    else setSelectedCategories(selectedCategories.filter(c => c !== cat));
                  }
                }))
              ])
            })
          ]),
          // Status filter
          el('div', { style: { flex: '1 1 auto', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666' } }, __('Filter by Status', 'vaptsecure')),
            el('div', { style: { display: 'flex', gap: '10px', flexWrap: 'wrap' } },
              [
                { label: __('All', 'vaptsecure'), value: 'all' },
                { label: __('Develop', 'vaptsecure'), value: 'develop' },
                { label: __('Release', 'vaptsecure'), value: 'release' },
                { label: __('Draft', 'vaptsecure'), value: 'draft' },
                { label: __('Test', 'vaptsecure'), value: 'test' },
              ].map(opt => el('label', { key: opt.value, style: { display: 'flex', alignItems: 'center', gap: '4px', cursor: 'pointer', fontSize: '11px' } }, [
                el('input', {
                  type: 'radio',
                  name: fromArchive ? 'vaptsecure_filter_archive_status' : 'vaptsecure_filter_active_status',
                  value: opt.value,
                  checked: filterStatus === opt.value,
                  onChange: () => setFilterStatus(opt.value),
                  style: { margin: 0 }
                }),
                opt.label
              ]))
            )
          ])
        ])
      ]);
    };

    const renderTable = (backups, selected, total, page, setPage, fromArchive) => {
      const processed = processBackups(backups, fromArchive);
      const totalPages = Math.ceil(processed.length / perPage);
      const clampedPage = Math.min(page, Math.max(1, totalPages));
      const startIdx = (clampedPage - 1) * perPage;
      const endIdx = Math.min(clampedPage * perPage, processed.length);
      const pageItems = processed.slice(startIdx, endIdx);
      const allSelected = pageItems.length > 0 && pageItems.every(i => selected.includes(i.feature_key));

      if (clampedPage !== page) {
        setTimeout(() => setPage(clampedPage), 0);
      }

      return el('div', { style: { background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', width: '100%' } }, [
        renderFilterBar(backups, fromArchive),
        el('div', { style: { padding: '15px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
          el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } }, [
            el(CheckboxControl, { 
              checked: allSelected, 
              onChange: () => toggleSelectAll(pageItems, fromArchive),
              __nextHasNoMarginBottom: true
            }),
            el('span', { style: { fontSize: '13px', color: '#64748b' } }, 
              sprintf(__('%d selected', 'vaptsecure'), selected.length)
            )
          ]),
          el('div', { style: { display: 'flex', gap: '10px' } }, [
            !fromArchive && selected.length > 0 && el(Button, { 
              isSecondary: true, 
              isSmall: true,
              onClick: () => openModal('archive', false)
            }, __('Archive Selected', 'vaptsecure')),
            fromArchive && selected.length > 0 && el(Button, { 
              isPrimary: true, 
              isSmall: true,
              onClick: () => openModal('restore', true)
            }, __('Restore Selected', 'vaptsecure')),
            selected.length > 0 && el(Button, { 
              isDestructive: true, 
              isSmall: true,
              onClick: () => openModal('purge', fromArchive)
            }, __('Purge Selected', 'vaptsecure'))
          ])
        ]),
        el('table', { className: 'wp-list-table widefat fixed striped', style: { border: 'none', width: '100%' } }, [
          el('thead', null, el('tr', null, [
            el('th', { style: { width: '40px' } }, ''),
            el('th', { 
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => {
                if (sortBy === 'feature_key') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
                else { setSortBy('feature_key'); setSortOrder('asc'); }
              }
            }, __('Feature Key', 'vaptsecure'), renderSortIcon('feature_key')),
            el('th', { 
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => {
                if (sortBy === 'feature_category') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
                else { setSortBy('feature_category'); setSortOrder('asc'); }
              }
            }, __('Category', 'vaptsecure'), renderSortIcon('feature_category')),
            el('th', { 
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => {
                if (sortBy === 'feature_title') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
                else { setSortBy('feature_title'); setSortOrder('asc'); }
              }
            }, __('Feature Title', 'vaptsecure'), renderSortIcon('feature_title')),
            el('th', { 
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => {
                if (sortBy === 'status') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
                else { setSortBy('status'); setSortOrder('asc'); }
              }
            }, fromArchive ? __('Status at Archive', 'vaptsecure') : __('Status', 'vaptsecure'), renderSortIcon('status')),
            el('th', { 
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => {
                if (sortBy === 'date') setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
                else { setSortBy('date'); setSortOrder('desc'); }
              }
            }, fromArchive ? __('Archived Date', 'vaptsecure') : __('Last Backup', 'vaptsecure'), renderSortIcon('date')),
            el('th', { style: { width: '180px' } }, __('Actions', 'vaptsecure'))
          ])),
          el('tbody', null, renderRows(pageItems, selected, fromArchive))
        ]),
        renderPagination(total, clampedPage, setPage, processed)
      ]);
    };

    const renderModal = () => {
      if (!modal) return null;

      const isPurge = modal.type === 'purge';
      const isArchive = modal.type === 'archive';
      const title = isPurge ? 
        (modal.fromArchive ? __('Confirm Purge from Archive', 'vaptsecure') : __('Confirm Purge Backups', 'vaptsecure')) :
        isArchive ? __('Confirm Archive Backup', 'vaptsecure') :
        __('Confirm Restore', 'vaptsecure');
      
      const message = isPurge ?
        sprintf(__('Are you sure you want to permanently delete %d backup record(s)? This action cannot be undone.', 'vaptsecure'), modal.items.length) :
        isArchive ?
        sprintf(__('Are you sure you want to move %d backup(s) to archive? They can be restored later if needed.', 'vaptsecure'), modal.items.length) :
        sprintf(__('Are you sure you want to restore %d feature(s) from archive? They will be restored to their original status.', 'vaptsecure'), modal.items.length);

      return el(Modal, {
        title,
        onRequestClose: () => !processing && setModal(null),
        style: { maxWidth: '500px' }
      }, [
        el('p', { style: { marginBottom: '20px', color: '#374151' } }, message),
        el('ul', { style: { marginBottom: '20px', paddingLeft: '20px', maxHeight: '200px', overflowY: 'auto' } }, 
          modal.items.map(item => el('li', { key: item.feature_key, style: { marginBottom: '5px', fontFamily: 'monospace' } }, item.feature_key))
        ),
        el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px' } }, [
          el(Button, { 
            disabled: processing,
            onClick: () => setModal(null)
          }, __('Cancel', 'vaptsecure')),
          el(Button, { 
            isPrimary: isPurge ? false : true,
            isDestructive: isPurge,
            disabled: processing,
            onClick: executeAction
          }, processing ? __('Processing...', 'vaptsecure') : (isPurge ? __('Confirm Purge', 'vaptsecure') : isArchive ? __('Confirm Archive', 'vaptsecure') : __('Confirm Restore', 'vaptsecure')))
        ])
      ]);
    };

    if (loading && activeBackups.length === 0 && archivedBackups.length === 0) {
      return el('div', { style: { padding: '40px', textAlign: 'center' } }, [
        el(Spinner),
        el('p', null, __('Loading Backup Archive...', 'vaptsecure'))
      ]);
    }

    if (error) {
      return el(Notice, { status: 'error', isDismissible: true, onRemove: () => setError(null) }, [el('p', null, error)]);
    }

    const tabs = [
      { name: 'active', title: sprintf(__('Active Backups (%d)', 'vaptsecure'), activeTotal) },
      { name: 'archived', title: sprintf(__('Archived Backups (%d)', 'vaptsecure'), archivedTotal) }
    ];

    return el('div', { className: 'vapt-backup-archive-wrap' }, [
      el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' } }, [
        el('div', { style: { display: 'flex', gap: '10px' } }, [
          missingCount > 0 && el(Button, { 
            isPrimary: true, 
            disabled: syncing,
            onClick: syncExisting
          }, syncing ? __('Syncing...', 'vaptsecure') : sprintf(__('Generate Backups for %d Existing Features', 'vaptsecure'), missingCount)),
          el(Button, { 
            isSecondary: true, 
            disabled: resyncing,
            onClick: resyncTitles
          }, resyncing ? __('Resyncing...', 'vaptsecure') : __('Re-Sync Titles & Categories', 'vaptsecure'))
        ]),
        el('div', { style: { fontSize: '13px', color: '#64748b' } }, 
          sprintf(__('Total: %d active, %d archived', 'vaptsecure'), activeTotal, archivedTotal)
        )
      ]),

      el(TabPanel, {
        className: 'vapt-backup-tabs',
        activeClass: 'is-active',
        tabs,
        onSelect: (tabName) => {
          if (tabName === 'active') fetchActiveBackups(activePage);
          else fetchArchivedBackups(archivedPage);
        }
      }, (tab) => {
        if (tab.name === 'active') {
          return renderTable(activeBackups, activeSelected, activeTotal, activePage, setActivePage, false);
        } else {
          return renderTable(archivedBackups, archivedSelected, archivedTotal, archivedPage, setArchivedPage, true);
        }
      }),

      renderModal()
    ]);
  };

  // Mount the React app
  const mountApp = () => {
    const container = document.getElementById('vapt-backup-archive-root');
    if (container) {
      vaptBackupLog.log('Container found, rendering app');
      try {
        render(el(BackupArchivePage), container);
      } catch (e) {
        vaptBackupLog.error('Render failed:', e);
        container.innerHTML = '<div class="notice notice-error"><p>Backup Archive UI failed to load. Check console for details.</p></div>';
      }
    } else {
      vaptBackupLog.error('Container #vapt-backup-archive-root not found');
    }
  };

  // Use wp.domReady if available, otherwise mount directly
  if (wp.domReady) {
    wp.domReady(mountApp);
  } else {
    // Script is in footer, DOM should be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mountApp);
    } else {
      mountApp();
    }
  }
})();
