(function () {
  if (typeof wp === 'undefined' || !wp.element || !wp.components || !wp.i18n) return;

  const { useState, useEffect, useMemo, Fragment, createElement: el } = wp.element;
  const { __, sprintf } = wp.i18n || {};
  const components = wp.components || {};

  const {
    Button,
    Card,
    CardBody,
    CardHeader,
    CheckboxControl,
    Dashicon,
    Modal,
    Placeholder,
    SelectControl,
    TextControl,
    ToggleControl,
  } = {
    Button: components.Button,
    Card: components.Card,
    CardBody: components.CardBody,
    CardHeader: components.CardHeader,
    CheckboxControl: components.CheckboxControl,
    Dashicon: components.Dashicon,
    Modal: components.Modal,
    Placeholder: components.Placeholder,
    SelectControl: components.SelectControl,
    TextControl: components.TextControl,
    ToggleControl: components.ToggleControl,
  };

  if (!useState || !useEffect || !useMemo || !Fragment || !el || !__ || !sprintf) return;

  const DomainFeatures = ({ domains = [], features = [], isDomainModalOpen, selectedDomain, setDomainModalOpen, setSelectedDomain, updateDomainFeatures, addDomain, deleteDomain, batchDeleteDomains, setConfirmState, selectedDomains = [], setSelectedDomains }) => {
    const isSuper = (window.vaptSecureSettings && window.vaptSecureSettings.isSuper) || false;

    const [newDomain, setNewDomain] = useState('');
    const [isWildcardNew, setIsWildcardNew] = useState(false);
    const [activeCategory, setActiveCategory] = useState('all');
    const [severityFilters, setSeverityFilters] = useState([]);
    const [sortConfig, setSortConfig] = useState({ key: 'domain', direction: 'asc' });
    const [isEditModalOpen, setEditModalOpen] = useState(false);
    const [editDomainData, setEditDomainData] = useState({ id: '', domain: '', is_wildcard: false, is_enabled: true });
    const [viewFeaturesModalOpen, setViewFeaturesModalOpen] = useState(false);
    const [viewFeaturesModalDomain, setViewFeaturesModalDomain] = useState(null);

    const toggleDomainSelection = (id) => {
      const current = selectedDomains || [];
      if (current.includes(id)) {
        setSelectedDomains(current.filter(i => i !== id));
      } else {
        setSelectedDomains([...current, id]);
      }
    };

    const sortedDomains = useMemo(() => {
      const sortable = [...(domains || [])];
      if (sortConfig.key !== null) {
        sortable.sort((a, b) => {
          let valA = a[sortConfig.key];
          let valB = b[sortConfig.key];

          if (sortConfig.key === 'is_wildcard') {
            valA = (valA === '1' || valA === true || valA === 1) ? 1 : 0;
            valB = (valB === '1' || valB === true || valB === 1) ? 1 : 0;
          }

          if (valA < valB) return sortConfig.direction === 'asc' ? -1 : 1;
          if (valA > valB) return sortConfig.direction === 'asc' ? 1 : -1;
          return 0;
        });
      }
      return sortable;
    }, [domains, sortConfig]);

    const requestSort = (key) => {
      let direction = 'asc';
      if (sortConfig.key === key && sortConfig.direction === 'asc') direction = 'desc';
      setSortConfig({ key, direction });
    };

    const SortIndicator = ({ column }) => {
      if (sortConfig.key !== column) return el(Dashicon, { icon: 'sort', size: 14, style: { opacity: 0.3, marginLeft: '5px' } });
      return el(Dashicon, {
        icon: sortConfig.direction === 'asc' ? 'arrow-up-alt2' : 'arrow-down-alt2',
        size: 14,
        style: { marginLeft: '5px', color: '#2271b1' }
      });
    };

    const filteredBySeverity = useMemo(() => {
      return (features || []).filter(f => {
        if (isSuper && !f.is_from_active_file) {
          const s = f.status ? f.status.toLowerCase() : 'draft';
          if (s === 'draft' || s === 'default' || !s) return false;
        }

        const s = f.status ? f.status.toLowerCase() : 'draft';
        const normalizedStatus = (s === 'implemented') ? 'release' : s;
        if (normalizedStatus !== 'release') return false;

        let severityLevel = 'medium';
        if (f.severity !== null && f.severity !== undefined) {
          if (typeof f.severity === 'object' && f.severity.level !== undefined) {
            severityLevel = f.severity.level.toLowerCase();
          } else if (typeof f.severity === 'string') {
            severityLevel = f.severity.toLowerCase();
          }
        }
        return (severityFilters || []).includes(severityLevel);
      });
    }, [features, severityFilters, isSuper]);

    const availableSeverityLevels = useMemo(() => {
      const releaseStateFeatures = (features || []).filter(f => {
        const s = f.status ? f.status.toLowerCase() : 'draft';
        const normalizedStatus = (s === 'implemented') ? 'release' : s;
        return normalizedStatus === 'release';
      });

      const severitySet = new Set();
      releaseStateFeatures.forEach(f => {
        let severityLevel = 'medium';
        if (f.severity !== null && f.severity !== undefined) {
          if (typeof f.severity === 'object' && f.severity.level !== undefined) {
            severityLevel = f.severity.level.toLowerCase();
          } else if (typeof f.severity === 'string') {
            severityLevel = f.severity.toLowerCase();
          }
        }
        severitySet.add(severityLevel);
      });

      const orderedSeverities = ['critical', 'high', 'medium', 'low'];
      return orderedSeverities.filter(level => severitySet.has(level));
    }, [features]);

    useEffect(() => {
      if (isDomainModalOpen && availableSeverityLevels.length > 0) {
        setSeverityFilters([...availableSeverityLevels]);
      }
    }, [isDomainModalOpen, availableSeverityLevels]);

    const categories = useMemo(() => {
      const cats = [...new Set(filteredBySeverity.map(f => f.category || 'Uncategorized'))].sort();
      return cats;
    }, [filteredBySeverity]);

    const displayFeatures = useMemo(() => {
      const filtered = filteredBySeverity || [];
      if (activeCategory === 'all') return filtered;
      return filtered.filter(f => (f.category || 'Uncategorized') === activeCategory);
    }, [filteredBySeverity, activeCategory]);

    const featuresByCategory = useMemo(() => {
      const grouped = {};
      (displayFeatures || []).forEach(f => {
        const cat = f.category || 'Uncategorized';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(f);
      });
      const sortedResult = {};
      Object.keys(grouped).sort().forEach(key => {
        sortedResult[key] = grouped[key];
      });
      return sortedResult;
    }, [displayFeatures]);

    const domainStats = useMemo(() => {
      const doms = Array.isArray(domains) ? domains : [];
      return {
        total: doms.length,
        active: doms.filter(d => !(d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0)).length,
        disabled: doms.filter(d => (d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0)).length
      };
    }, [domains]);

    return el(Fragment, null, [
      el('div', {
        className: 'vapt-domain-features-header',
        style: {
          padding: '8px 16px',
          background: '#fff',
          borderBottom: '1px solid #dcdcde',
          marginBottom: '0'
        }
      }, el('h2', {
        style: { margin: 0, fontSize: '14px', fontWeight: 600, color: '#1e1e1e' }
      }, __('Domain Specific Features', 'vaptsecure'))),
      el('div', { className: 'vapt-domain-features-body', style: { padding: '12px 0' } }, [
        el('div', {
          style: {
            display: 'flex',
            gap: '15px',
            padding: '6px 15px',
            background: '#fff',
            border: '1px solid #dcdcde',
            borderRadius: '4px',
            marginBottom: '10px',
            alignItems: 'center',
            fontSize: '11px',
            color: '#333'
          }
        }, [
          el('span', { style: { fontWeight: '700', textTransform: 'uppercase', fontSize: '10px', color: '#666' } }, __('Summary:', 'vaptsecure')),
          el('span', { style: { fontWeight: '600', color: '#2271b1' } }, sprintf(__('Total Domains: %d', 'vaptsecure'), domainStats.total)),
          el('span', { style: { color: '#46b450', fontWeight: '700' } }, sprintf(__('Active: %d', 'vaptsecure'), domainStats.active)),
          el('span', { style: { color: '#d63638', fontWeight: '600' } }, sprintf(__('Disabled: %d', 'vaptsecure'), domainStats.disabled)),
        ]),
        el('table', { key: 'table', className: 'wp-list-table widefat fixed striped' }, [
          el('thead', null, el('tr', null, [
            el('th', { style: { width: '40px' } }, el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
              el(CheckboxControl, {
                checked: (domains || []).length > 0 && (selectedDomains || []).length === (domains || []).length,
                indeterminate: (selectedDomains || []).length > 0 && (selectedDomains || []).length < (domains || []).length,
                onChange: (val) => setSelectedDomains(val ? (domains || []).map(d => d.id) : []),
                __nextHasNoMarginBottom: true
              }),
              el('span', { style: { fontSize: '10px', opacity: 0.6, fontWeight: 600, whiteSpace: 'nowrap' } }, __('ALL', 'vaptsecure'))
            ])),
            el('th', {
              style: { cursor: 'pointer', userSelect: 'none' },
              onClick: () => requestSort('domain')
            }, [
              __('Domain', 'vaptsecure'),
              el(SortIndicator, { column: 'domain' })
            ]),
            el('th', { style: { width: '100px' } }, __('Status', 'vaptsecure')),
            el('th', {
              style: { width: '180px', cursor: 'pointer', userSelect: 'none' },
              onClick: () => requestSort('is_wildcard')
            }, [
              __('Type', 'vaptsecure'),
              el(SortIndicator, { column: 'is_wildcard' })
            ]),
            el('th', { style: { width: '120px' } }, __('License', 'vaptsecure')),
            el('th', null, __('Features Enabled', 'vaptsecure')),
            el('th', { style: { width: '120px' } }, __('Expiry Date', 'vaptsecure')),
            el('th', { style: { width: '220px' } }, __('Actions', 'vaptsecure'))
          ])),
          el('tbody', null, sortedDomains.map((d) => el('tr', { key: d.id }, [
            el('td', null, el(CheckboxControl, {
              checked: (selectedDomains || []).includes(d.id),
              onChange: () => toggleDomainSelection(d.id),
              __nextHasNoMarginBottom: true
            })),
            el('td', null, el('strong', null, d.domain)),
            el('td', null, el(Button, {
              isLink: true,
              onClick: () => {
                const currentEnabled = !(d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0);
                addDomain(d.domain, (d.is_wildcard === '1' || d.is_wildcard === true || d.is_wildcard === 1), !currentEnabled, d.id);
              },
              style: { color: (d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0) ? '#d63638' : '#00a32a', fontWeight: 600, textDecoration: 'none' },
              title: __('Click to toggle domain status', 'vaptsecure')
            }, [
              el(Dashicon, { icon: (d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0) ? 'hidden' : 'visibility', size: 16, style: { marginRight: '4px' } }),
              (d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0) ? __('Disabled', 'vaptsecure') : __('Active', 'vaptsecure')
            ])),
            el('td', null, el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
              el(Button, {
                isLink: true,
                onClick: (e) => {
                  e.preventDefault();
                  const currentWildcard = (d.is_wildcard === '1' || d.is_wildcard === true || d.is_wildcard === 1);
                  const nextWildcard = !currentWildcard;
                  addDomain(d.domain, nextWildcard, !(d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0), d.id);
                },
                style: { textDecoration: 'none', color: (d.is_wildcard === '1' || d.is_wildcard === true || d.is_wildcard === 1) ? '#2271b1' : '#64748b', fontWeight: 600 },
                title: __('Click to toggle domain type', 'vaptsecure')
              }, (d.is_wildcard === '1' || d.is_wildcard === true || d.is_wildcard === 1) ? __('Wildcard', 'vaptsecure') : __('Standard', 'vaptsecure')),
              el(Dashicon, { icon: 'update', size: 14, style: { opacity: 0.5 } })
            ])),
            el('td', null, el('span', {
              style: {
                display: 'inline-block',
                padding: '2px 8px',
                borderRadius: '4px',
                fontSize: '11px',
                fontWeight: 600,
                textTransform: 'capitalize',
                background: (d.license_type === 'developer' || d.license_type === 'developer_unbound') ? '#f3e8ff' : (d.license_type === 'pro' ? '#fff1f2' : '#f1f5f9'),
                color: (d.license_type === 'developer' || d.license_type === 'developer_unbound') ? '#6b21a8' : (d.license_type === 'pro' ? '#be123c' : '#475569'),
                border: '1px solid transparent'
              }
            }, d.license_type || 'Standard')),
            el('td', null, (Array.isArray(d.features) && d.features.length > 0) ? el(Button, {
              isLink: true,
              onClick: (e) => {
                e.preventDefault();
                setViewFeaturesModalDomain(d);
                setViewFeaturesModalOpen(true);
              }
            }, (() => {
              // Count only Release state features for display
              const releaseFeatures = (Array.isArray(d.features) ? d.features : []).filter(fKey => {
                const feature = (features || []).find(f => f.key === fKey);
                return feature && feature.status && (
                  feature.status === 'Release' ||
                  feature.status === 'release' ||
                  feature.status === 'implemented'
                );
              });
              return `${releaseFeatures.length} ${__('Features', 'vaptsecure')} `;
            })()) : `${(Array.isArray(d.features) ? d.features.length : 0)} ${__('Features', 'vaptsecure')} `),
            el('td', null, el('span', { style: { fontSize: '12px', color: (d.license_type !== 'developer' && d.license_type !== 'developer_unbound' && d.manual_expiry_date && new Date(d.manual_expiry_date) < new Date()) ? '#dc2626' : 'inherit' } },
              (d.license_type === 'developer' || d.license_type === 'developer_unbound')
                ? __('Never', 'vaptsecure')
                : (d.manual_expiry_date ? new Date(d.manual_expiry_date).toLocaleDateString() : '-')
            )),
            el('td', null, el('div', { style: { display: 'flex', gap: '8px' } }, [
              el(Button, {
                isSecondary: true,
                isSmall: true,
                onClick: () => {
                  setEditDomainData({
                    id: d.id,
                    domain: d.domain,
                    is_wildcard: (d.is_wildcard === '1' || d.is_wildcard === true || d.is_wildcard === 1),
                    is_enabled: !(d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0)
                  });
                  setEditModalOpen(true);
                }
              }, __('Edit', 'vaptsecure')),
              el(Button, {
                isSecondary: true,
                isSmall: true,
                onClick: () => { setSelectedDomain(d); setDomainModalOpen(true); }
              }, __('Manage Features', 'vaptsecure')),
              el(Button, {
                isDestructive: true,
                isSmall: true,
                onClick: () => {
                  setConfirmState({
                    message: sprintf(__('Are you sure you want to delete the domain "%s"? This action cannot be undone.', 'vaptsecure'), d.domain),
                    onConfirm: () => {
                      deleteDomain(d.id);
                      setConfirmState(null);
                    },
                    isDestructive: true
                  });
                }
              }, __('Delete', 'vaptsecure'))
            ]))
          ])))
        ]),
        isEditModalOpen && el(Modal, {
          title: __('Edit Domain Settings', 'vaptsecure'),
          onRequestClose: () => setEditModalOpen(false),
          style: { maxWidth: '500px' }
        }, [
          el('div', { style: { padding: '10px 0' } }, [
            el(TextControl, {
              label: __('Domain Name', 'vaptsecure'),
              value: editDomainData.domain,
              onChange: (val) => setEditDomainData({ ...editDomainData, domain: val })
            }),
            el(SelectControl, {
              label: __('Type', 'vaptsecure'),
              value: editDomainData.is_wildcard ? 'wildcard' : 'standard',
              options: [
                { label: __('Standard', 'vaptsecure'), value: 'standard' },
                { label: __('Wildcard (*.domain)', 'vaptsecure'), value: 'wildcard' }
              ],
              onChange: (val) => setEditDomainData({ ...editDomainData, is_wildcard: val === 'wildcard' })
            }),
            el(ToggleControl, {
              label: __('Enabled', 'vaptsecure'),
              checked: editDomainData.is_enabled,
              onChange: (val) => setEditDomainData({ ...editDomainData, is_enabled: val }),
              help: __('Enable or disable all VAPT features for this domain.', 'vaptsecure')
            }),
            el('div', { style: { marginTop: '20px', display: 'flex', justifyContent: 'flex-end', gap: '10px' } }, [
              el(Button, { isSecondary: true, onClick: () => setEditModalOpen(false) }, __('Cancel', 'vaptsecure')),
              el(Button, {
                isPrimary: true,
                onClick: () => {
                  addDomain(editDomainData.domain, editDomainData.is_wildcard, editDomainData.is_enabled, editDomainData.id);
                  setEditModalOpen(false);
                }
              }, __('Update Domain', 'vaptsecure'))
            ])
          ])
        ]),
        isDomainModalOpen && selectedDomain && el(Modal, {
          key: 'modal',
          title: sprintf(__('Features for %s', 'vaptsecure'), selectedDomain.domain),
          onRequestClose: () => setDomainModalOpen(false),
          className: 'vapt-domain-features-modal',
          style: { maxWidth: '1400px', width: '90%' }
        }, [
          el('div', {
            style: {
              marginBottom: '20px',
              padding: '12px 20px',
              background: '#f8fafc',
              borderRadius: '8px',
              border: '1px solid #e2e8f0',
              display: 'flex',
              alignItems: 'center',
              gap: '20px'
            }
          }, [
            el('span', { style: { fontSize: '11px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase' } }, __('Severity Level:')),
            el(Button, {
              isPrimary: (severityFilters || []).length !== availableSeverityLevels.length,
              variant: (severityFilters || []).length === availableSeverityLevels.length ? 'secondary' : 'primary',
              onClick: () => {
                if ((severityFilters || []).length === availableSeverityLevels.length) setSeverityFilters([]);
                else setSeverityFilters([...availableSeverityLevels]);
              },
              style: {
                fontWeight: 700,
                padding: '8px 20px',
                height: 'auto',
                boxShadow: (severityFilters || []).length !== availableSeverityLevels.length ? '0 2px 4px rgba(34, 113, 177, 0.2)' : 'none'
              }
            }, (severityFilters || []).length === availableSeverityLevels.length ? __('Reset All Filters', 'vaptsecure') : __('Select All Severities', 'vaptsecure')),
            el('div', { style: { display: 'flex', gap: '15px', paddingLeft: '20px', borderLeft: '2px solid #e2e8f0' } },
              availableSeverityLevels.map(level => {
                const labelMap = {
                  critical: __('Critical', 'vaptsecure'),
                  high: __('High', 'vaptsecure'),
                  medium: __('Medium', 'vaptsecure'),
                  low: __('Low', 'vaptsecure')
                };
                return el(CheckboxControl, {
                  key: level,
                  label: labelMap[level] || level,
                  checked: severityFilters.includes(level),
                  onChange: (val) => {
                    if (val) setSeverityFilters([...severityFilters, level]);
                    else if ((severityFilters || []).length > 1) setSeverityFilters(severityFilters.filter(v => v !== level));
                  },
                  __nextHasNoMarginBottom: true
                });
              })
            )
          ]),

          el('div', { style: { display: 'flex', gap: '0', height: '60vh', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden' } }, [
            el('aside', {
              style: {
                width: '240px',
                flexShrink: 0,
                background: '#fcfcfd',
                borderRight: '1px solid #e2e8f0',
                padding: '20px 0',
                overflowY: 'auto'
              }
            }, [
              el('div', { style: { padding: '0 20px 10px', fontSize: '11px', fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.05em' } }, __('Feature Categories')),
              el('div', { id: 'vapt-domain-features-sidebar-categories', style: { display: 'flex', flexDirection: 'column' } }, [
                el('a', {
                  id: 'vapt-category-link-all',
                  href: '#',
                  onClick: (e) => { e.preventDefault(); setActiveCategory('all'); },
                  className: 'vapt-sidebar-link' + (activeCategory === 'all' ? ' is-active' : ''),
                  style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '10px 20px',
                    textDecoration: 'none',
                    color: activeCategory === 'all' ? '#2271b1' : '#64748b',
                    background: activeCategory === 'all' ? '#eff6ff' : 'transparent',
                    fontWeight: activeCategory === 'all' ? 600 : 500,
                    fontSize: '13px',
                    borderRight: activeCategory === 'all' ? '3px solid #2271b1' : 'none'
                  }
                }, [
                  el('span', null, __('All Categories', 'vaptsecure')),
                  el('span', { style: { fontSize: '10px', padding: '2px 6px', borderRadius: '10px', background: activeCategory === 'all' ? '#dbeafe' : '#f1f5f9' } }, (Array.isArray(filteredBySeverity) ? filteredBySeverity : []).length)
                ]),
                ...categories.map(cat => {
                  const count = (Array.isArray(filteredBySeverity) ? filteredBySeverity : []).filter(f => (f.category || 'Uncategorized') === cat).length;
                  const isActive = activeCategory === cat;
                  return el('a', {
                    key: cat,
                    href: '#',
                    onClick: (e) => { e.preventDefault(); setActiveCategory(cat); },
                    className: 'vapt-sidebar-link' + (isActive ? ' is-active' : ''),
                    style: {
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '10px 20px',
                      textDecoration: 'none',
                      color: isActive ? '#2271b1' : '#64748b',
                      background: isActive ? '#eff6ff' : 'transparent',
                      fontWeight: isActive ? 600 : 500,
                      fontSize: '13px',
                      borderRight: isActive ? '3px solid #2271b1' : 'none',
                      whiteSpace: 'nowrap',
                      overflow: 'visible'
                    }
                  }, [
                    el('span', { style: { overflow: 'hidden', textOverflow: 'ellipsis' } }, cat),
                    el('span', { style: { fontSize: '10px', padding: '2px 6px', borderRadius: '10px', background: isActive ? '#dbeafe' : '#f1f5f9', marginLeft: '8px', flexShrink: 0 } }, count)
                  ]);
                })
              ])
            ]),
            el('div', {
              style: {
                flexGrow: 1,
                padding: '25px',
                background: '#fff',
                overflowY: 'auto'
              }
            }, [
              ((Array.isArray(displayFeatures) ? displayFeatures : []).length === 0) ? el('div', { style: { textAlign: 'center', padding: '40px', color: '#94a3b8' } }, __('No features matching the current selection.', 'vaptsecure')) :
                Object.entries(featuresByCategory).map(([catName, catFeatures]) => el(Fragment, { key: catName }, [
                  el('h3', { className: 'vapt-category-header' }, [
                    el(Dashicon, { icon: 'category', size: 16 }),
                    catName
                  ]),
                  el('div', { className: 'vapt-feature-grid' }, catFeatures.map(f => el('div', {
                    key: f.key,
                    className: `vapt - domain - feature - card ${f.exists_in_multiple_files ? 'vapt-feature-multi-file' : (f.is_from_active_file === false ? 'vapt-feature-inactive-only' : '')} `,
                    style: {
                      padding: '20px',
                      border: '1px solid #e2e8f0',
                      borderRadius: '12px',
                      background: '#fff',
                      display: 'flex',
                      flexDirection: 'column',
                      transition: 'all 0.3s',
                      boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                    }
                  }, [
                    el('div', { style: { marginBottom: '20px' } }, [
                      el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' } }, [
                        el('h4', { style: { margin: 0, fontSize: '16px', fontWeight: 700, color: '#1e293b' } }, f.label),
                        el('span', {
                          className: `vapt - status - pill status - ${(f.status || '').toLowerCase()} `,
                          style: {
                            fontSize: '9px',
                            fontWeight: 700,
                            textTransform: 'uppercase',
                            padding: '2px 8px',
                            borderRadius: '4px',
                            color: '#fff',
                            background: (f.status === 'Develop' || f.status === 'develop') ? '#10b981' :
                              (f.status === 'Test' || f.status === 'test') ? '#eab308' :
                                (f.status === 'Release' || f.status === 'release' || f.status === 'implemented') ? '#f97316' : '#94a3b8',
                            border: 'none',
                            boxShadow: '0 1px 2px rgba(0,0,0,0.1)'
                          }
                        }, f.status)
                      ]),
                      el('p', { style: { margin: 0, fontSize: '13px', color: '#64748b', lineHeight: '1.5' } }, f.description)
                    ]),
                    el('div', {
                      id: `vapt - domain - feature - footer - ${f.key} `,
                      style: {
                        marginTop: 'auto',
                        paddingTop: '15px',
                        borderTop: '1px solid #f1f5f9',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between'
                      }
                    }, [
                      el('span', { id: `vapt - domain - feature - status - text - ${f.key} `, style: { fontSize: '12px', fontWeight: 600, color: '#475569' } }, (Array.isArray(selectedDomain.features) ? selectedDomain.features : []).includes(f.key) ? __('Active', 'vaptsecure') : __('Disabled', 'vaptsecure')),
                      el(ToggleControl, {
                        checked: (Array.isArray(selectedDomain.features) ? selectedDomain.features : []).includes(f.key),
                        onChange: (val) => {
                          const newFeats = val
                            ? [...(Array.isArray(selectedDomain.features) ? selectedDomain.features : []), f.key]
                            : (Array.isArray(selectedDomain.features) ? selectedDomain.features : []).filter(k => k !== f.key);
                          updateDomainFeatures(selectedDomain.id, newFeats);
                          setSelectedDomain({ ...selectedDomain, features: newFeats });
                        },
                        __nextHasNoMarginBottom: true,
                        style: { margin: 0 }
                      })
                    ])
                  ])))
                ]))
            ])
          ]),
          el('div', { style: { marginTop: '20px', textAlign: 'right' } }, el(Button, {
            isPrimary: true,
            onClick: () => setDomainModalOpen(false)
          }, __('Done', 'vaptsecure')))
        ]),
        viewFeaturesModalOpen && viewFeaturesModalDomain && el(Modal, {
          id: 'vapt-view-features-modal',
          title: sprintf(__('Enabled Features for %s', 'vaptsecure'), viewFeaturesModalDomain.domain),
          onRequestClose: () => setViewFeaturesModalOpen(false),
          style: { maxWidth: '1200px', width: '90%' }
        }, [
          (() => {
            const releaseFeatures = (features || []).filter(f => {
              const isEnabledForDomain = (Array.isArray(viewFeaturesModalDomain.features) ? viewFeaturesModalDomain.features : []).includes(f.key);
              const isReleaseState = f.status && (
                f.status === 'Release' ||
                f.status === 'release' ||
                f.status === 'implemented'
              );
              return isEnabledForDomain && isReleaseState;
            });

            return releaseFeatures.length > 0
              ? el('div', {
                id: 'vapt-view-features-grid-wrap',
                style: {
                  display: 'grid',
                  gridTemplateColumns: 'repeat(3, 1fr)',
                  gap: '20px',
                  padding: '20px',
                  maxHeight: '70vh',
                  overflowY: 'auto'
                }
              },
                releaseFeatures.map(f =>
                  el(Card, {
                    key: f.key,
                    style: { border: '1px solid #e2e8f0', borderRadius: '8px', boxShadow: 'sm' }
                  }, [
                    el(CardHeader, {
                      style: {
                        background: '#f8fafc',
                        borderBottom: '1px solid #e2e8f0',
                        padding: '12px 16px',
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'flex-start',
                        gap: '5px'
                      }
                    }, [
                      el('span', {
                        style: {
                          fontSize: '9px',
                          fontWeight: 600,
                          textTransform: 'uppercase',
                          padding: '2px 6px',
                          borderRadius: '4px',
                          background: '#e2e8f0',
                          color: '#475569'
                        }
                      }, f.category || 'General'),
                      el('strong', { style: { fontSize: '13px', color: '#1e293b' } }, f.label)
                    ]),
                    el(CardBody, { style: { padding: '16px' } }, [
                      el('div', { style: { marginBottom: '10px' } }, [
                        el('span', {
                          style: {
                            display: 'inline-block',
                            fontSize: '10px',
                            fontWeight: 700,
                            textTransform: 'uppercase',
                            padding: '3px 8px',
                            borderRadius: '12px',
                            color: '#fff',
                            background: (f.status === 'Develop' || f.status === 'develop') ? '#10b981' :
                              (f.status === 'Test' || f.status === 'test') ? '#eab308' :
                                (f.status === 'Release' || f.status === 'release' || f.status === 'implemented') ? '#f97316' : '#94a3b8',
                            boxShadow: '0 1px 2px rgba(0,0,0,0.1)'
                          }
                        }, f.status || 'Unknown')
                      ]),
                      el('p', { style: { fontSize: '12px', color: '#64748b', margin: 0, lineHeight: '1.5' } }, f.description)
                    ])
                  ])
                )
              )
              : el('div', {
                style: {
                  padding: '40px 20px',
                  textAlign: 'center',
                  color: '#64748b',
                  fontSize: '14px'
                }
              }, __('No Release State features enabled for this domain.', 'vaptsecure'));
          })(),
          el('div', { style: { marginTop: '20px', textAlign: 'right', borderTop: '1px solid #e2e8f0', paddingTop: '15px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
            el('div', { style: { display: 'flex', gap: '10px' } }, [
              el(Button, {
                isDestructive: true,
                onClick: () => {
                  // Reset features to only include Release state features
                  const releaseFeatureKeys = (features || [])
                    .filter(f => f.status && (
                      f.status === 'Release' ||
                      f.status === 'release' ||
                      f.status === 'implemented'
                    ))
                    .map(f => f.key);
                  
                  updateDomainFeatures(viewFeaturesModalDomain.id, releaseFeatureKeys);
                  setViewFeaturesModalDomain({ ...viewFeaturesModalDomain, features: releaseFeatureKeys });
                  setViewFeaturesModalOpen(false);
                }
              }, __('Reset to Release Features', 'vaptsecure'))
            ]),
            el(Button, { isPrimary: true, onClick: () => setViewFeaturesModalOpen(false) }, __('Close', 'vaptsecure'))
          ])
        ])
      ])
    ]);
  };

  window.VAPTSECURE_DomainFeatures = window.VAPTSECURE_DomainFeatures || DomainFeatures;
})();
