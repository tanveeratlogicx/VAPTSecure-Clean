var vaptLog = window.vaptLog || {
  log: (...args) => window.VAPT_DEBUG && console.log('[VAPT]', ...args),
  warn: (...args) => window.VAPT_DEBUG && console.warn('[VAPT]', ...args),
  error: (...args) => console.error('[VAPT]', ...args),
  debug: (...args) => window.VAPT_DEBUG && console.debug('[VAPT]', ...args),
  info: (...args) => window.VAPT_DEBUG && console.info('[VAPT]', ...args)
};

(function () {
  if (typeof wp === 'undefined') {
    vaptLog.error('"wp" global is missing!');
    return;
  }

  const { render, useState, useEffect, useMemo, useRef, Fragment, createElement: el } = wp.element || {};
  const components = wp.components || {};
  const {
    TabPanel, Panel, PanelBody, PanelRow, Button, Dashicon,
    ToggleControl, SelectControl, Modal, TextControl, Spinner,
    Notice, Placeholder, Dropdown, CheckboxControl, BaseControl, Icon,
    TextareaControl, Card, CardHeader, CardBody, Tooltip
  } = {
    TabPanel: components.TabPanel || components.__experimentalTabPanel,
    Panel: components.Panel,
    PanelBody: components.PanelBody,
    PanelRow: components.PanelRow,
    Button: components.Button,
    Dashicon: components.Dashicon,
    ToggleControl: components.ToggleControl,
    SelectControl: components.SelectControl,
    Modal: components.Modal,
    TextControl: components.TextControl,
    Spinner: components.Spinner,
    Notice: components.Notice,
    Placeholder: components.Placeholder,
    Dropdown: components.Dropdown,
    CheckboxControl: components.CheckboxControl,
    BaseControl: components.BaseControl,
    Icon: components.Icon,
    TextareaControl: components.TextareaControl,
    Card: components.Card,
    CardHeader: components.CardHeader,
    CardBody: components.CardBody,
    Tooltip: components.Tooltip || components.__experimentalTooltip
  };
  const settings = window.vaptSecureSettings || {};
  const isSuper = settings.isSuper || false;
  const renderFatal = (message) => {
    vaptLog.error(message);
    const container = document.getElementById('vapt-admin-root');
    if (!container) return;
    container.innerHTML = `<div class="notice notice-error"><p>${message}</p></div>`;
  };

  const apiFetch = wp.apiFetch;
  const { __, sprintf } = wp.i18n || {};
  const Generator = window.VAPTSECURE_Generator;
  const GeneratedInterface = window.VAPTSECURE_GeneratedInterface;

  if (!wp.element || !wp.components || !wp.apiFetch || !wp.i18n) {
    renderFatal('One or more WordPress dependencies are missing!');
    return;
  }

  const ErrorBoundary = window.VAPTSECURE_ErrorBoundary;
  const VAPTSECURE_AlertModal = window.VAPTSECURE_AlertModal;
  const VAPTSECURE_ConfirmModal = window.VAPTSECURE_ConfirmModal;

  if (!ErrorBoundary || !VAPTSECURE_AlertModal || !VAPTSECURE_ConfirmModal) {
    renderFatal('One or more UI modules are missing!');
    return;
  }

  const HistoryModal = window.VAPTSECURE_HistoryModal;
  const DesignModal = window.VAPTSECURE_DesignModal;

  if (!HistoryModal || !DesignModal) {
    renderFatal('One or more feature modules are missing (design-modal).');
    return;
  }

  // Prompt Configuration Modal
  const PromptConfigModal = ({ isOpen, onClose, feature, designPromptConfig, setDesignPromptConfig, selectedFile }) => {
    const [promptText, setPromptText] = useState(
      designPromptConfig ? (typeof designPromptConfig === 'string' ? designPromptConfig : JSON.stringify(designPromptConfig, null, 2)) : ''
    );

    const handleSave = () => {
      setDesignPromptConfig(promptText);
      onClose();
    };

    return el(Modal, {
      title: __('AI Design Prompt Configuration', 'vaptsecure'),
      onRequestClose: onClose,
      className: 'vapt-prompt-config-modal'
    }, [
      el('p', null, __('Customize the instructions sent to the AI for interface generation.', 'vaptsecure')),
      el(TextareaControl, {
        label: __('System Prompt / Context Template', 'vaptsecure'),
        value: promptText,
        onChange: setPromptText,
        rows: 20,
        style: { fontFamily: 'monospace', fontSize: '12px' }
      }),
      el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '15px' } }, [
        el(Button, { isSecondary: true, onClick: onClose }, __('Cancel', 'vaptsecure')),
        el(Button, { isPrimary: true, onClick: handleSave }, __('Save Configuration', 'vaptsecure'))
      ])
    ]);
  };

  const FieldMappingModal = window.VAPTSECURE_FieldMappingModal;

  if (!FieldMappingModal) {
    renderFatal('One or more feature modules are missing (field-mapping).');
    return;
  }

  // Transition Note Modal Component
  const TransitionNoteModal = ({ transitioning, onConfirm, onCancel }) => {
    const [formValues, setFormValues] = useState({
      note: transitioning.note || '',
      dev_instruct: transitioning.dev_instruct || '',
      wireframeUrl: transitioning.wireframeUrl || ''
    });
    const [modalSaveStatus, setModalSaveStatus] = useState(null);

    return el(Modal, {
      title: sprintf(__('Transition to %s', 'vaptsecure'), transitioning.nextStatus),
      onRequestClose: onCancel,
      className: 'vapt-transition-modal',
      style: {
        width: '600px',
        maxWidth: '95%',
        maxHeight: '800px',
        overflow: 'hidden'
      }
    }, [
      el('div', {
        style: { height: '100%', display: 'flex', flexDirection: 'column' },
        onPaste: (e) => {
          if (transitioning.nextStatus !== 'Develop') return;
          const items = (e.clipboardData || e.originalEvent.clipboardData).items;
          for (let index in items) {
            const item = items[index];
            if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
              const blob = item.getAsFile();
              setModalSaveStatus({ message: __('Uploading pasted image...', 'vaptsecure'), type: 'info' });

              const formData = new FormData();
              formData.append('file', blob);
              formData.append('title', 'Pasted Wireframe - ' + transitioning.key);

              wp.apiFetch({
                path: 'vaptsecure/v1/upload-media',
                method: 'POST',
                body: formData
              }).then(res => {
                setFormValues({ ...formValues, wireframeUrl: res.url });
                setModalSaveStatus({ message: __('Image Uploaded', 'vaptsecure'), type: 'success' });
              }).catch(err => {
                setModalSaveStatus({ message: __('Paste failed', 'vaptsecure'), type: 'error' });
              });
            }
          }
        }
      }, [
        el('div', { style: { flexGrow: 1, paddingBottom: '10px' } }, [
          el('p', { style: { fontWeight: '600', marginBottom: '10px' } }, sprintf(__('Moving "%s" to %s.', 'vaptsecure'), transitioning.key, transitioning.nextStatus)),

          el(TextareaControl, {
            label: __('Internal Transition Note', 'vaptsecure'),
            help: __('Reason for status change, logged in history.', 'vaptsecure'),
            value: formValues.note,
            onChange: (val) => setFormValues({ ...formValues, note: val }),
          }),

          transitioning.nextStatus === 'Develop' && el(Fragment, null, [
            el(TextareaControl, {
              label: __('Development Instructions (AI Guidance)', 'vaptsecure'),
              help: __('AI-ready brief for workbench generation (VAPTSchema patterns).', 'vaptsecure'),
              value: formValues.dev_instruct,
              onChange: (val) => setFormValues({ ...formValues, dev_instruct: val }),
            }),
            el(TextControl, {
              label: __('Wireframe / Design URL', 'vaptsecure'),
              value: formValues.wireframeUrl,
              onChange: (val) => setFormValues({ ...formValues, wireframeUrl: val }),
              help: __('Paste image from clipboard directly into this modal.', 'vaptsecure')
            }),
            modalSaveStatus && el(Notice, {
              status: modalSaveStatus.type,
              isDismissible: false
            }, modalSaveStatus.message)
          ])
        ]),

        el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', paddingTop: '15px', borderTop: '1px solid #ddd' } }, [
          el(Button, { isSecondary: true, onClick: onCancel }, __('Cancel', 'vaptsecure')),
          el(Button, {
            isPrimary: true,
            onClick: () => onConfirm(formValues)
          }, sprintf(__('Confirm to %s', 'vaptsecure'), transitioning.nextStatus))
        ])
      ])
    ]);
  };

  // Batch Revert Modal Component (v1.9.2)
  const BatchRevertModal = ({ isOpen, previewData, isLoading, isExecuting, includeBroken, onToggleIncludeBroken, includeRelease, onToggleIncludeRelease, onRefresh, onConfirm, onCancel }) => {
    if (!isOpen) return null;

    const count = previewData?.count || 0;
    const brokenCount = previewData?.broken_count || 0;
    const developCount = previewData?.develop_count || 0;
    const releaseCount = previewData?.release_count || 0;
    const includedBrokenCount = previewData?.included_broken_count || 0;
    const includedReleaseCount = previewData?.included_release_count || 0;
    const features = previewData?.features || [];
    const totalHistory = previewData?.total_history_records || 0;
    const totalSchema = previewData?.total_with_schema || 0;
    const totalImpl = previewData?.total_with_impl || 0;
    const totalEnforced = previewData?.total_enforced || 0;

    return el(Modal, {
      title: __('Batch Revert to Draft - Preview', 'vaptsecure'),
      onRequestClose: onCancel,
      className: 'vapt-batch-revert-modal',
      style: { width: '650px', maxWidth: '95vw' }
    }, [
      // Condition 1: Cold start (No data and loading)
      !previewData && isLoading ?
        el('div', { style: { padding: '40px', textAlign: 'center' } }, [
          el(Spinner, null),
          el('p', { style: { marginTop: '10px' } }, __('Analyzing features...', 'vaptsecure'))
        ]) :
        [
          // Control Toggles (Always visible if counts exist)
          brokenCount > 0 && el('div', {
            key: 'toggle-broken',
            style: { background: '#f0f6fc', padding: '12px', borderRadius: '4px', marginBottom: '15px', border: '1px solid #2271b1' }
          }, [
            el(ToggleControl, {
              label: sprintf(__('Include %d broken feature(s) (Draft status with history records)', 'vaptsecure'), brokenCount),
              checked: includeBroken,
              onChange: (val) => onToggleIncludeBroken(val),
              disabled: isExecuting || isLoading
            }),
            el('p', {
              style: { margin: '5px 0 0 0', fontSize: '11px', color: '#646970', fontStyle: 'italic' }
            }, __('Broken features are in Draft status but have leftover history records from incomplete transitions.', 'vaptsecure'))
          ]),

          releaseCount > 0 && el('div', {
            key: 'toggle-release',
            style: { background: '#f0f9f0', padding: '12px', borderRadius: '4px', marginBottom: '15px', border: '1px solid #00a32a' }
          }, [
            el(ToggleControl, {
              label: sprintf(__('Include %d Release feature(s)', 'vaptsecure'), releaseCount),
              checked: includeRelease,
              onChange: (val) => onToggleIncludeRelease(val),
              disabled: isExecuting || isLoading
            }),
            el('p', {
              style: { margin: '5px 0 0 0', fontSize: '11px', color: '#646970', fontStyle: 'italic' }
            }, __('Release features are currently active in production. Reverting them will disable enforcement.', 'vaptsecure'))
          ]),

          // Dynamic Preview Area
          el('div', {
            key: 'dynamic-content',
            style: { position: 'relative', opacity: isLoading ? 0.6 : 1, transition: 'opacity 0.2s' }
          }, [
            // Overlay Spinner for Ajax refresh
            isLoading && previewData && el('div', {
              style: {
                position: 'absolute',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                zIndex: 10,
                background: 'rgba(255,255,255,0.7)',
                padding: '10px',
                borderRadius: '50%'
              }
            }, el(Spinner)),

            count === 0 ?
              el('div', { key: 'no-features', style: { padding: '20px', textAlign: 'center' } }, [
                el('p', { style: { fontSize: '16px', color: '#646970' } },
                  __('✓ No features in selected statuses to revert.', 'vaptsecure'))
              ]) :
              [
                // Summary Section
                el('div', {
                  key: 'summary',
                  style: { background: '#f6f7f7', padding: '15px', borderRadius: '4px', marginBottom: '15px' }
                }, [
                  el('h3', {
                    style: { margin: '0 0 10px 0', fontSize: '14px', textTransform: 'uppercase', letterSpacing: '0.5px', color: '#1e1e1e' }
                  }, __('Summary of Changes', 'vaptsecure')),
                  el('div', {
                    style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', fontSize: '13px' }
                  }, [
                    el('div', null, [
                      el('strong', null, developCount),
                      __(' Develop features', 'vaptsecure'),
                      includeBroken && includedBrokenCount > 0 && el('span', { style: { color: '#856404' } }, sprintf(__(' + %d broken', 'vaptsecure'), includedBrokenCount)),
                      includeRelease && includedReleaseCount > 0 && el('span', { style: { color: '#d63638' } }, sprintf(__(' + %d release', 'vaptsecure'), includedReleaseCount))
                    ]),
                    el('div', null, [el('strong', { style: { color: '#d63638' } }, totalHistory), __(' history records will be deleted', 'vaptsecure')]),
                    el('div', null, [el('strong', { style: { color: '#d63638' } }, totalSchema), __(' generated schemas will be cleared', 'vaptsecure')]),
                    el('div', null, [el('strong', { style: { color: '#d63638' } }, totalEnforced), __(' enforced features will be disabled', 'vaptsecure')]),
                  ])
                ]),

                // Warning
                el('div', {
                  key: 'warning',
                  style: { background: '#fcf0f1', border: '1px solid #d63638', padding: '12px', borderRadius: '4px', marginBottom: '15px' }
                }, [
                  el('p', {
                    style: { margin: 0, color: '#d63638', fontWeight: '600', fontSize: '13px' }
                  }, __('⚠️ Warning: This action is IRREVERSIBLE. All history and implementation data will be permanently deleted.', 'vaptsecure'))
                ]),

                // Feature List Table
                el('div', {
                  key: 'table-container',
                  style: { maxHeight: '250px', overflow: 'auto', border: '1px solid #ddd', borderRadius: '4px', marginBottom: '15px' }
                }, [
                  el('table', {
                    style: { width: '100%', borderCollapse: 'collapse', fontSize: '12px' }
                  }, [
                    el('thead', {
                      style: { background: '#f6f7f7', position: 'sticky', top: 0, zIndex: 1 }
                    }, [
                      el('tr', null, [
                        el('th', { style: { padding: '8px', textAlign: 'left', borderBottom: '1px solid #ddd' } }, __('Feature', 'vaptsecure')),
                        el('th', { style: { padding: '8px', textAlign: 'center', borderBottom: '1px solid #ddd', width: '60px' } }, __('Status', 'vaptsecure')),
                        el('th', { style: { padding: '8px', textAlign: 'center', borderBottom: '1px solid #ddd', width: '60px' } }, __('History', 'vaptsecure')),
                        el('th', { style: { padding: '8px', textAlign: 'center', borderBottom: '1px solid #ddd', width: '50px' } }, __('Schema', 'vaptsecure')),
                        el('th', { style: { padding: '8px', textAlign: 'center', borderBottom: '1px solid #ddd', width: '50px' } }, __('Impl', 'vaptsecure')),
                      ])
                    ]),
                    el('tbody', null,
                      features.slice(0, 20).map((f, idx) =>
                        el('tr', {
                          key: f.feature_key || idx,
                          style: { borderBottom: '1px solid #eee', background: f.is_broken ? '#fff3cd' : 'transparent' }
                        }, [
                          el('td', { style: { padding: '8px', textAlign: 'left' } }, f.title || f.feature_key),
                          el('td', {
                            style: { padding: '8px', textAlign: 'center', fontSize: '10px', fontWeight: '600' }
                          }, f.is_broken ? el('span', { style: { color: '#856404' } }, 'BROKEN') :
                            (f.is_release ? el('span', { style: { color: '#00a32a' } }, 'Release') : el('span', { style: { color: '#2271b1' } }, 'Develop'))),
                          el('td', { style: { padding: '8px', textAlign: 'center' } }, f.history_records),
                          el('td', {
                            style: { padding: '8px', textAlign: 'center', color: f.has_generated_schema ? '#d63638' : '#999' }
                          }, f.has_generated_schema ? '✓' : '-'),
                          el('td', {
                            style: { padding: '8px', textAlign: 'center', color: f.has_implementation_data ? '#d63638' : '#999' }
                          }, f.has_implementation_data ? '✓' : '-'),
                        ])
                      )
                    )
                  ]),
                  features.length > 20 && el('p', {
                    style: { fontStyle: 'italic', color: '#646970', margin: '8px', fontSize: '12px' }
                  }, sprintf(__('...and %d more features', 'vaptsecure'), features.length - 20))
                ]),

                // Action Buttons
                el('div', {
                  key: 'actions',
                  style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', paddingTop: '15px', marginTop: '15px', borderTop: '2px solid #ddd' }
                }, [
                  el(Button, {
                    variant: 'secondary',
                    onClick: onCancel,
                    disabled: isExecuting,
                    style: { minWidth: '80px' }
                  }, __('Cancel', 'vaptsecure')),
                  el(Button, {
                    variant: 'primary',
                    isDestructive: true,
                    isBusy: isExecuting,
                    disabled: isExecuting || count === 0,
                    onClick: onConfirm,
                    style: { minWidth: '180px', background: '#d63638', borderColor: '#d63638' }
                  }, isExecuting
                    ? __('Reverting...', 'vaptsecure')
                    : sprintf(__('⚠️ Execute Revert (%d features)', 'vaptsecure'), count))
                ])
              ]
          ])
        ]
    ]);
  };

  // Backward Transition Warning Modal
  const BackwardTransitionModal = ({ isOpen, onConfirm, onCancel, type }) => {
    if (!isOpen) return null;

    let title = __('Warning', 'vaptsecure');
    let message = '';
    let confirmLabel = __('Confirm', 'vaptsecure');
    let isProduction = false;

    if (type === 'reset') {
      title = __('Reset to Draft?', 'vaptsecure');
      message = __('Warning: innovative "Clean Slate" protocol. Transitioning to Draft will **permanently delete** all implementation data, generated schemas, and history logs for this feature. This cannot be undone.', 'vaptsecure');
      confirmLabel = __('Confirm Reset (Wipe Data)', 'vaptsecure');
      checkboxLabel = __('I understand all history will be lost', 'vaptsecure');
    } else if (type === 'production_regression') {
      title = __('⚠️ Production Impact Warning', 'vaptsecure');
      message = __('You are demoting a **Released** feature. This feature may be active on multiple production sites.\n\nReverting to Test implies a potential defect that could impact live environments.', 'vaptsecure');
      confirmLabel = __('Confirm Production Regression', 'vaptsecure');
      checkboxLabel = __('I acknowledge this may impact live sites', 'vaptsecure');
      isProduction = true;
    } else {
      title = __('Confirm Regression', 'vaptsecure');
      // Added customization warning as requested
      message = __('Warning: You are moving this feature back to a previous stage within the cycle.\n\nPending verifications will be invalidated.\n**You may lose any customization applied to the Feature.**', 'vaptsecure');
      confirmLabel = __('Confirm Regression', 'vaptsecure');
      checkboxLabel = __('I acknowledge potential loss of customization', 'vaptsecure');
    }

    const [acknowledged, setAcknowledged] = useState(false);

    return el(Modal, {
      title: title,
      onRequestClose: onCancel,
      className: 'vapt-warning-modal',
      style: { maxWidth: '500px' }
    }, [
      el('div', { style: { padding: '20px' } }, [
        el('div', { style: { display: 'flex', gap: '15px', alignItems: 'flex-start' } }, [
          el(Icon, { icon: 'warning', size: 36, style: { color: isProduction ? '#d63638' : '#d97706' } }),
          el('div', null, [
            el('p', { style: { marginTop: 0, fontSize: '13px', lineHeight: '1.5', whiteSpace: 'pre-line' }, dangerouslySetInnerHTML: { __html: message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') } }),
            // Checkbox is now unconditional for all regressions
            el('div', { style: { marginTop: '15px', display: 'flex', alignItems: 'flex-start' } }, [
              el(CheckboxControl, {
                label: checkboxLabel,
                checked: acknowledged,
                onChange: setAcknowledged,
                style: { marginBottom: 0 }
              })
            ])
          ])
        ]),
        el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '25px' } }, [
          el(Button, { isSecondary: true, onClick: onCancel }, __('Cancel', 'vaptsecure')),
          el(Button, {
            isDestructive: true,
            disabled: !acknowledged, // Mandatory for all types
            onClick: onConfirm
          }, confirmLabel)
        ])
      ])
    ]);
  };

  // Lifecycle Indicator Component
  const LifecycleIndicator = ({ feature, onChange, onDirectUpdate }) => {
    const activeStep = feature.status;
    const [warningState, setWarningState] = useState(null); // { type, nextStatus }

    const steps = [
      { id: 'Draft', label: __('Draft', 'vaptsecure') },
      { id: 'Develop', label: __('Develop', 'vaptsecure') },
      { id: 'Release', label: __('Release', 'vaptsecure') }
    ];

    const getStepValue = (status) => {
      const map = { 'Draft': 0, 'Develop': 1, 'Release': 2 };
      return map[status] || 0; // Default to 0 if unknown
    };

    const handleSelection = (nextStatus) => {
      const currentVal = getStepValue(activeStep);
      const nextVal = getStepValue(nextStatus);

      if (nextVal < currentVal) {
        // PCR: Backward Transition Warning
        let type = 'regression';
        if (nextStatus === 'Draft') type = 'reset';

        setWarningState({ type, nextStatus });
      } else {
        onChange(nextStatus);
      }
    };

    return el(Fragment, null, [
      el('div', { id: `vapt - lifecycle - controls - ${feature.key} `, className: 'vapt-flex-row', style: { fontSize: '12px' } }, [
        ...steps.map((step) => {
          const isChecked = step.id === activeStep;
          return el('label', {
            id: `vapt - lifecycle - label - ${feature.key} -${step.id} `,
            key: step.id,
            style: { cursor: 'pointer', color: isChecked ? '#2271b1' : 'inherit', fontWeight: isChecked ? '600' : 'normal' },
            className: 'vapt-flex-row'
          }, [
            el('input', {
              id: `vapt - lifecycle - radio - ${feature.key} -${step.id} `,
              type: 'radio',
              name: `lifecycle_${feature.key || feature.id}_${Math.random()} `,
              checked: isChecked,
              onChange: () => handleSelection(step.id),
              style: { margin: 0 }
            }),
            step.label
          ]);
        })
      ]),
      warningState && el(BackwardTransitionModal, {
        isOpen: true,
        type: warningState.type,
        onCancel: () => setWarningState(null),
        onConfirm: () => {
          const status = warningState.nextStatus;
          setWarningState(null);

          if (onDirectUpdate) {
            const isReset = status === 'Draft';
            const updates = {
              status: status,
              history_note: isReset ? 'History Reset by User (Clean Slate)' : 'Regression Confirmed'
            };

            if (isReset) {
              updates.reset_history = true;
              updates.has_history = false;
              updates.generated_schema = null;
              updates.implementation_data = null;
              updates.wireframe_url = '';
              updates.include_verification_engine = 0;
              updates.include_verification_guidance = 0;
            }

            onDirectUpdate(feature.key || feature.id, updates);
          } else {
            onChange(status); // Fallback if prop not provided
          }
        }
      })
    ]);
  };

  const DomainFeatures = window.VAPTSECURE_DomainFeatures;

  if (!DomainFeatures) {
    renderFatal('One or more feature modules are missing (domains).');
    return;
  }


  // FieldRow MUST live outside BuildGenerator — if defined inside, 
  // React creates a new component type on every render, causing unmount/remount
  // on every keystroke and destroying input focus.
  const FieldRow = ({ label, children }) => el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } }, [
    el('label', { style: { width: '85px', fontSize: '12px', fontWeight: '500', color: '#64748b', flexShrink: 0 } }, label),
    el('div', { style: { flex: 1 } }, children)
  ]);

  const bumpSemverPatch = (version) => {
    const value = String(version || '').trim();
    const match = value.match(/^(\d+)\.(\d+)\.(\d+)$/);
    if (!match) return '';
    return `${match[1]}.${match[2]}.${parseInt(match[3], 10) + 1}`;
  };

  const BuildGenerator = ({ domains, features, activeFile, setAlertState }) => {
    const [buildDomain, setBuildDomain] = useState('');
    const [buildVersion, setBuildVersion] = useState(settings.pluginVersion || '3.1.0');
    const [requireWP, setRequireWP] = useState('6.0');
    const [requirePHP, setRequirePHP] = useState('7.4.33');
    const [includeConfig, setIncludeConfig] = useState(true);
    const [includeData, setIncludeData] = useState(true);
    const [securityAlertEmail, setSecurityAlertEmail] = useState(settings.adminEmail || '');
    const [whiteLabel, setWhiteLabel] = useState({
      name: 'VAPTSecure',
      description: '',
      author: 'Tanveer Malik',
      plugin_uri: 'https://vaptsecure.net',
      author_uri: '#',
      text_domain: 'vapt-secure'
    });
    // Local draft state: captures typed values without triggering effects on every keystroke.
    // Fields bind to draftLabel for display/onChange, and commit to whiteLabel on onBlur.
    const [draftLabel, setDraftLabel] = useState({
      name: 'VAPTSecure',
      author: 'Tanveer Malik',
      plugin_uri: 'https://vaptsecure.net',
      author_uri: '#'
    });
    // Keep draftLabel in sync if whiteLabel updates externally (e.g. on initial load)
    const whiteLabelRef = useRef(whiteLabel);
    useEffect(() => {
      if (
        whiteLabelRef.current.name !== whiteLabel.name ||
        whiteLabelRef.current.author !== whiteLabel.author ||
        whiteLabelRef.current.plugin_uri !== whiteLabel.plugin_uri ||
        whiteLabelRef.current.author_uri !== whiteLabel.author_uri
      ) {
        setDraftLabel({
          name: whiteLabel.name,
          author: whiteLabel.author,
          plugin_uri: whiteLabel.plugin_uri,
          author_uri: whiteLabel.author_uri
        });
      }
      whiteLabelRef.current = whiteLabel;
    }, [whiteLabel]);

    const [generating, setGenerating] = useState(false);
    const [downloadUrl, setDownloadUrl] = useState(null);
    const [importedAt, setImportedAt] = useState(null);
    const [licenseScope, setLicenseScope] = useState('single');
    const [installationLimit, setInstallationLimit] = useState(1);
    const restrictFeatures = false;
    const suggestedNextVersion = useMemo(() => bumpSemverPatch(buildVersion), [buildVersion]);

    // Calculate enabled release features for selected domain (v3.2.1)
    const enabledReleaseCount = useMemo(() => {
      if (!buildDomain) return 0;
      const selectedDomain = (Array.isArray(domains) ? domains : []).find(d => d.domain === buildDomain);
      if (!selectedDomain || !Array.isArray(selectedDomain.features)) return 0;
      
      const domainFeatureKeys = new Set(selectedDomain.features);
      const allFeatures = Array.isArray(features) ? features : [];
      
      return allFeatures.filter(f => 
        domainFeatureKeys.has(f.key) && 
        (f.status === 'Release' || f.status === 'release' || f.status === 'implemented')
      ).length;
    }, [buildDomain, domains, features]);

    // Auto-Generation Effect — keep the build tab aligned to the selected domain and editable metadata
    useEffect(() => {
      const slug = whiteLabel.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      const allFeatures = Array.isArray(features) ? features : [];
      const featCount = allFeatures.filter(f =>
        f.status === 'Release' || f.status === 'release' || f.status === 'implemented'
      ).length;

      const isUniversalBuild = buildDomain === '*' || (buildDomain || '').indexOf('__universal__:') === 0;
      const domainContext = buildDomain
        ? (isUniversalBuild ? `a universal security hardening package` : `a dedicated security build for ${buildDomain}`)
        : `a universal security hardening package`;
      const desc = `${whiteLabel.name} is ${domainContext}, delivering specialized Vulnerability Assessment and Penetration Testing (VAPT) protection against OWASP Top 10 vulnerabilities. ` +
        `This build integrates ${featCount} active security modules tailored for WordPress environments. ` +
        `Requires PHP ${requirePHP} or higher and WordPress ${requireWP}+; optimized for Apache/.htaccess, Nginx, Cloudflare, and PHP script enforcement. ` +
        `Generated by ${whiteLabel.name}.`;

      // Only update text_domain and description — do NOT update the fields the user is editing
      setWhiteLabel(prev => ({ ...prev, text_domain: slug, description: desc }));

      // Only sync imported-at metadata here; version is intentionally user-driven.
      const selectedDomain = (Array.isArray(domains) ? domains : []).find(d => d.domain === buildDomain);
      if (selectedDomain) {
        if (selectedDomain.imported_at) setImportedAt(selectedDomain.imported_at);
        else setImportedAt(null);
        if (selectedDomain.version) {
          setBuildVersion(String(selectedDomain.version));
        }
      } else {
        setImportedAt(null);
      }
      // Only runs when committed values change — NOT on every keystroke
    }, [whiteLabel.name, whiteLabel.author, whiteLabel.plugin_uri, whiteLabel.author_uri, buildDomain, domains, features]);

    const runBuild = (type = 'full_build') => {
      if (!buildDomain && type !== 'config_only') {
        setAlertState({ message: __('Please select a target domain.', 'vaptsecure'), type: 'error' });
        return;
      }
      setGenerating(true);
      setDownloadUrl(null);
      const selectedDomain = (Array.isArray(domains) ? domains : []).find(d => d.domain === buildDomain);
      const buildFeatures = selectedDomain ? (Array.isArray(selectedDomain.features) ? selectedDomain.features : []) : (Array.isArray(features) ? features : []).filter(f => f.status === 'implemented').map(f => f.key);
      const buildLicenseType = selectedDomain && selectedDomain.license_type ? selectedDomain.license_type : 'standard';
      const buildIsWildcard = selectedDomain ? (selectedDomain.is_wildcard === '1' || selectedDomain.is_wildcard === true || selectedDomain.is_wildcard === 1) : false;

      apiFetch({
        path: 'vaptsecure/v1/build/generate',
        method: 'POST',
        data: {
          domain: buildDomain.trim(),
          version: buildVersion.trim(),
          require_wp: requireWP.trim(),
          require_php: requirePHP.trim(),
          features: buildFeatures,
          generate_type: type,
          include_config: includeConfig,
          include_data: includeData,
          package_policy: 'minimum_runtime',
          include_ai_support: false,
          security_alert_email: securityAlertEmail,
          license_type: buildLicenseType,
          license_scope: licenseScope,
          installation_limit: installationLimit,
          restrict_features: false,
          is_wildcard: buildIsWildcard,
          white_label: {
            name: whiteLabel.name.trim(),
            description: whiteLabel.description.trim(),
            author: whiteLabel.author.trim(),
            plugin_uri: whiteLabel.plugin_uri.trim(),
            author_uri: whiteLabel.author_uri.trim(),
            text_domain: whiteLabel.text_domain.trim()
          }
        }
      }).then((res) => {
        if (res && res.download_url) {
          window.location.href = res.download_url;
          setAlertState({ message: __('Build generated and downloading!', 'vaptsecure'), type: 'success' });
        } else {
          setAlertState({ message: __('Build failed: No download URL received.', 'vaptsecure'), type: 'error' });
        }
        setGenerating(false);
      }).catch((error) => {
        setGenerating(false);
        setAlertState({ message: __('Build failed! ' + (error.message || ''), 'vaptsecure'), type: 'error' });
      });
    };

    const saveToServer = () => {
      if (!buildDomain) {
        setAlertState({ message: __('Please select a target domain.', 'vaptsecure'), type: 'error' });
        return;
      }
      setGenerating(true);
      const selectedDomain = (Array.isArray(domains) ? domains : []).find(d => d.domain === buildDomain);
      const buildFeatures = selectedDomain ? (Array.isArray(selectedDomain.features) ? selectedDomain.features : []) : [];
      const buildLicenseType = selectedDomain && selectedDomain.license_type ? selectedDomain.license_type : 'standard';

      apiFetch({
        path: 'vaptsecure/v1/build/save-config',
        method: 'POST',
        data: {
          domain: buildDomain.trim(),
          version: buildVersion.trim(),
          features: buildFeatures,
          license_type: buildLicenseType,
          license_scope: licenseScope,
          installation_limit: installationLimit,
          restrict_features: false
        }
      }).then(res => {
        if (res.success) {
          setAlertState({ message: __('Config saved to server successfully!', 'vaptsecure'), type: 'success' });
        } else {
          setAlertState({ message: __('Failed to save config.', 'vaptsecure'), type: 'error' });
        }
        setGenerating(false);
      }).catch(err => {
        setGenerating(false);
        const msg = (err && (err.message || (err.data && (err.data.message || err.data.error)))) || 'Unknown error';
        setAlertState({ message: 'Save failed: ' + msg, type: 'error' });
      });
    };

    const forceReImport = () => {
      if (!buildDomain) return;
      setGenerating(true);
      apiFetch({
        path: 'vaptsecure/v1/build/sync-config',
        method: 'POST',
        data: { domain: buildDomain }
      }).then(res => {
        if (res.success) {
          setImportedAt(res.imported_at);
          setAlertState({ message: `Config Re - Imported! Found ${res.features_count} features.`, type: 'success' });
        } else {
          setAlertState({ message: 'Import Failed: ' + (res.error || 'Unknown'), type: 'warning' });
        }
        setGenerating(false);
      }).catch(err => {
        setGenerating(false);
        setAlertState({ message: 'Import Error: ' + err.message, type: 'error' });
      });
    }

    return el('div', { className: 'vapt-build-generator' }, [
      el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '25px', marginTop: '30px' } }, [
        el(Icon, { icon: 'hammer', size: 24 }),
        el('h2', { style: { margin: 0, fontSize: '20px' } }, __('Generate New Build', 'vaptsecure'))
      ]),
      // 60/40 Layout
      el('div', { style: { display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: '25px', alignItems: 'start' } }, [

        // LEFT COLUMN: Configuration
        el(Card, { style: { display: 'flex', flexDirection: 'column', borderRadius: '8px', border: '1px solid #e2e8f0', height: '100%' } }, [
          el(CardHeader, { style: { background: '#f8fafc', borderBottom: '1px solid #e2e8f0', padding: '12px 20px' } }, [
            el('h3', { style: { margin: 0, fontSize: '15px', display: 'flex', alignItems: 'center', gap: '8px' } }, [
              el(Icon, { icon: 'admin-settings', size: 16 }),
              __('Configuration Details', 'vaptsecure')
            ])
          ]),
          el(CardBody, { style: { padding: '20px', display: 'flex', flexDirection: 'column', gap: '15px', flex: 1 } }, [
            // Domain & Config Toggle (Side-by-Side)
            el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px', padding: '12px 20px', background: '#f8fafc', borderRadius: '6px', border: '1px solid #e2e8f0' } }, [
              // LEFT GROUP: Target Domain + Scope Badge
              el('div', { style: { display: 'flex', alignItems: 'center', gap: '15px' } }, [
                el('label', { style: { fontSize: '12px', fontWeight: '500', color: '#64748b', marginRight: '5px' } }, __('Target Domain', 'vaptsecure')),
                el('div', { style: { width: '320px' } },
                  el(SelectControl, {
                    value: buildDomain,
                    options: [
                      { label: __('--- Select Target Domain ---', 'vaptsecure'), value: '' },
                      ...(Array.isArray(domains) ? domains : []).filter(d => {
                        const domainFeatureKeys = Array.isArray(d.features) ? d.features : [];
                        const releasedFeatureKeys = new Set(
                          (Array.isArray(features) ? features : [])
                            .filter(f => f.status === 'Release' || f.status === 'release' || f.status === 'implemented')
                            .map(f => f.key)
                        );
                        return domainFeatureKeys.some(key => releasedFeatureKeys.has(key));
                      }).map(d => ({
                        label: (d.domain === '*' || (d.domain || '').indexOf('__universal__:') === 0) ? __('Universal (Any Domain)', 'vaptsecure') : d.domain,
                        value: d.domain
                      }))
                    ],
                    onChange: (val) => {
                      setBuildDomain(val);
                      const dom = (Array.isArray(domains) ? domains : []).find(d => d.domain === val);
                      if (dom) setLicenseScope(dom.license_scope || 'single');
                    },
                    __nextHasNoMarginBottom: true,
                    style: { marginBottom: 0 }
                  })
                ),
                // Readonly License Scope Badge
                el('span', {
                  style: {
                    fontSize: '11px',
                    fontWeight: 600,
                    padding: '4px 12px',
                    borderRadius: '12px',
                    background: licenseScope === 'multisite' ? '#dbeafe' : '#dcfce7',
                    color: licenseScope === 'multisite' ? '#1d4ed8' : '#15803d',
                    border: `1px solid ${licenseScope === 'multisite' ? '#bfdbfe' : '#bbf7d0'}`,
                    whiteSpace: 'nowrap',
                    lineHeight: '1'
                  }
                }, licenseScope === 'multisite' ? __('Multi-Site', 'vaptsecure') : __('Single Domain', 'vaptsecure'))
              ]),

              // RIGHT GROUP: Configuration Toggles
              el('div', { style: { display: 'flex', alignItems: 'center', gap: '20px' } }, [
                // Include Config Toggle
                el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                  el('div', { style: { marginBottom: 0, lineHeight: 1 } },
                    el(ToggleControl, {
                      label: __('Include Config', 'vaptsecure'),
                      checked: includeConfig,
                      onChange: (val) => setIncludeConfig(val),
                      help: null,
                      __nextHasNoMarginBottom: true,
                      style: { marginBottom: 0 }
                    })
                  ),
                  el(Tooltip, { text: __('Include current feature configurations & security rules.', 'vaptsecure') },
                    el('span', { className: 'dashicons dashicons-editor-help', style: { fontSize: '14px', color: '#94a3b8', cursor: 'help' } })
                  )
                ]),
                // Include Active Data Toggle
                el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                  el('div', { style: { marginBottom: 0, lineHeight: 1 } },
                    el(ToggleControl, {
                      label: __('Include Active Data', 'vaptsecure'),
                      checked: includeData,
                      onChange: (val) => setIncludeData(val),
                      help: null,
                      __nextHasNoMarginBottom: true,
                      style: { marginBottom: 0 }
                    })
                  ),
                  el(Tooltip, { text: sprintf(__('Include Risk Catalog and definitions from active file: %s.', 'vaptsecure'), activeFile || 'Default') },
                    el('span', { className: 'dashicons dashicons-editor-help', style: { fontSize: '14px', color: '#94a3b8', cursor: 'help' } })
                  )
                ]),
                
              ])
            ]),

            // Enabled Features Count Display (v3.2.1)
            buildDomain && el('div', { style: { marginBottom: '20px', padding: '10px 20px', background: '#fffbeb', border: '1px solid #fef3c7', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '10px' } }, [
              el('span', { style: { fontSize: '16px' } }, '🚀'),
              el('div', { style: { fontSize: '13px', color: '#92400e', fontWeight: '500' } }, [
                sprintf(__('This domain has %d enabled features in "Release" state ready for build.', 'vaptsecure'), enabledReleaseCount)
              ])
            ]),

            // System Requirements Section (v3.2.1)
            el('div', { style: { padding: '15px 20px', background: '#f1f5f9', borderRadius: '6px', border: '1px solid #cbd5e1', marginBottom: '15px' } }, [
              el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '15px' } }, [
                el(Icon, { icon: 'admin-generic', size: 16 }),
                el('h4', { style: { margin: 0, fontSize: '13px', fontWeight: '600', color: '#334155' } }, __('System Requirements Defaults', 'vaptsecure'))
              ]),
              el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' } }, [
                el(FieldRow, { label: __('Require WP', 'vaptsecure') }, 
                  el(TextControl, {
                    value: requireWP,
                    onChange: setRequireWP,
                    placeholder: '6.0',
                    __nextHasNoMarginBottom: true
                  })
                ),
                el(FieldRow, { label: __('Require PHP', 'vaptsecure') }, 
                  el(TextControl, {
                    value: requirePHP,
                    onChange: setRequirePHP,
                    placeholder: '7.4.33',
                    __nextHasNoMarginBottom: true
                  })
                )
              ])
            ]),

            // Horizontal Fields in 2-Col Grid
            el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '40px', background: '#eef2f6', padding: '15px', borderRadius: '6px', border: '1px solid #e2e8f0' } }, [
              // Col 1
              el('div', null, [
                el(FieldRow, { label: __('Plugin Name', 'vaptsecure') },
                  el(TextControl, {
                    value: draftLabel.name,
                    onChange: (val) => setDraftLabel(prev => ({ ...prev, name: val })),
                    onBlur: () => setWhiteLabel(prev => ({ ...prev, name: draftLabel.name })),
                    style: { marginBottom: 0 }
                  })
                ),
                el(FieldRow, { label: __('Author', 'vaptsecure') },
                  el(TextControl, {
                    value: draftLabel.author,
                    onChange: (val) => setDraftLabel(prev => ({ ...prev, author: val })),
                    onBlur: () => setWhiteLabel(prev => ({ ...prev, author: draftLabel.author })),
                    style: { marginBottom: 0 }
                  })
                ),
                el(FieldRow, { label: __('Text Domain', 'vaptsecure') },
                  el(TextControl, { value: whiteLabel.text_domain, readOnly: true, style: { marginBottom: 0, background: '#f1f5f9' } })
                ),
              ]),
              // Col 2
              el('div', null, [
                el(FieldRow, { label: __('Plugin URI', 'vaptsecure') },
                  el(TextControl, {
                    value: draftLabel.plugin_uri,
                    onChange: (val) => setDraftLabel(prev => ({ ...prev, plugin_uri: val })),
                    onBlur: () => setWhiteLabel(prev => ({ ...prev, plugin_uri: draftLabel.plugin_uri })),
                    style: { marginBottom: 0 }
                  })
                ),
                el(FieldRow, { label: __('Author URI', 'vaptsecure') },
                  el(TextControl, {
                    value: draftLabel.author_uri,
                    onChange: (val) => setDraftLabel(prev => ({ ...prev, author_uri: val })),
                    onBlur: () => setWhiteLabel(prev => ({ ...prev, author_uri: draftLabel.author_uri })),
                    style: { marginBottom: 0 }
                  })
                ),
                el(FieldRow, { label: __('Version', 'vaptsecure') },
                  el(TextControl, { value: buildVersion, onChange: (val) => setBuildVersion(val), style: { marginBottom: 0 } })
                ),
                suggestedNextVersion && el('div', { style: { margin: '-2px 0 10px 85px', fontSize: '12px', color: '#64748b' } }, [
                  el('span', { style: { fontWeight: 600, color: '#0f172a' } }, __('Suggested next patch:', 'vaptsecure')),
                  ' ',
                  el('span', { style: { fontFamily: 'monospace', background: '#f1f5f9', padding: '1px 6px', borderRadius: '4px' } }, suggestedNextVersion),
                  el(Button, {
                    isSmall: true,
                    variant: 'link',
                    style: { marginLeft: '8px', height: 'auto', padding: 0 },
                    onClick: () => setBuildVersion(suggestedNextVersion)
                  }, __('Use suggestion', 'vaptsecure'))
                ]),
                el(FieldRow, { label: __('Alert Email', 'vaptsecure') },
                  el(TextControl, { value: securityAlertEmail, onChange: (val) => setSecurityAlertEmail(val), style: { marginBottom: 0 } })
                ),
              ]),
            ]),

            el('div', { style: { marginTop: '5px' } }, [
              el('label', { style: { display: 'block', fontSize: '12px', fontWeight: '500', color: '#64748b', marginBottom: '8px' } }, __('Plugin Description', 'vaptsecure')),
              el(TextareaControl, {
                value: whiteLabel.description,
                rows: 3,
                onChange: (val) => setWhiteLabel({ ...whiteLabel, description: val }),
                style: { marginBottom: '0', fontSize: '13px', lineHeight: '1.5' }
              })
            ]),

            el('div', { style: { display: 'flex', gap: '10px', marginTop: 'auto', paddingTop: '15px', borderTop: '1px solid #eee' } }, [
              el(Button, {
                isSecondary: true,
                style: { flex: 1, justifyContent: 'center' },
                onClick: saveToServer,
                disabled: generating || !buildDomain
              }, [
                el(Icon, { icon: 'upload', size: 18, style: { marginRight: '5px' } }),
                __('Save to Server', 'vaptsecure')
              ]),
              el(Button, {
                isPrimary: true,
                style: { flex: 1, justifyContent: 'center', background: '#357abd' },
                onClick: () => runBuild('full_build'),
                disabled: generating || !buildDomain
              }, [
                el(Icon, { icon: 'download', size: 18, style: { marginRight: '5px' } }),
                generating ? __('Generating...', 'vaptsecure') : __('Download Build', 'vaptsecure')
              ])
            ])
          ])
        ]),

        // RIGHT COLUMN: Status
        el(Card, { style: { borderRadius: '8px', border: '1px solid #e2e8f0', height: '100%', background: '#fff' } }, [
          el(CardHeader, { style: { background: '#f8fafc', borderBottom: '1px solid #e2e8f0', padding: '12px 20px' } }, [
            el('h4', { style: { margin: 0, fontSize: '14px', display: 'flex', alignItems: 'center', gap: '8px' } }, [
              el(Icon, { icon: 'info-outline', size: 16 }),
              __('Build Status & History', 'vaptsecure')
            ])
          ]),
          el(CardBody, { style: { padding: '20px' } }, [
            el('div', { style: { fontSize: '13px', color: '#64748b', lineHeight: '1.8' } }, [
              el('div', { style: { marginBottom: '10px', paddingBottom: '10px', borderBottom: '1px solid #eee', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
                el('strong', null, __('Generated Version', 'vaptsecure')),
                el('span', { style: { fontFamily: 'monospace', background: '#f1f5f9', padding: '2px 6px', borderRadius: '4px' } }, buildVersion)
              ]),
              el('div', { style: { marginBottom: '10px', paddingBottom: '10px', borderBottom: '1px solid #eee', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
                el('strong', null, __('Target Domain', 'vaptsecure')),
                el('span', {
                  style: {
                    fontSize: '11px',
                    fontWeight: 600,
                    padding: '2px 8px',
                    borderRadius: '4px',
                    background: buildDomain ? '#e0f2fe' : '#f1f5f9',
                    color: buildDomain ? '#0369a1' : '#64748b'
                  }
                }, (buildDomain === '*' || (buildDomain || '').indexOf('__universal__:') === 0) ? __('Universal', 'vaptsecure') : (buildDomain || __('None', 'vaptsecure')))
              ]),
              el('div', { style: { marginBottom: '10px', paddingBottom: '10px', borderBottom: '1px solid #eee', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
                el('strong', null, __('Active Features', 'vaptsecure')),
                el('span', { style: { fontWeight: '600', color: '#16a34a' } }, enabledReleaseCount + ' Modules')
              ]),
              el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
                el('strong', null, __('Last Import', 'vaptsecure')),
                el('span', { style: { fontSize: '11px', fontStyle: 'italic' } }, importedAt || __('Never', 'vaptsecure'))
              ])
            ]),
            el(Button, {
              isSecondary: true,
              style: { width: '100%', marginTop: '20px', justifyContent: 'center' },
              onClick: forceReImport,
              disabled: generating || !buildDomain || (buildDomain !== window.location.hostname && buildDomain !== window.location.hostname.replace(/^www\./, ''))
            }, __('Force Re-import from Server', 'vaptsecure')),
            el('p', {
              style: { fontSize: '11px', color: (buildDomain && buildDomain !== window.location.hostname && buildDomain !== window.location.hostname.replace(/^www\./, '')) ? '#ef4444' : '#94a3b8', marginTop: '10px', textAlign: 'center' }
            }, (buildDomain && buildDomain !== window.location.hostname && buildDomain !== window.location.hostname.replace(/^www\./, ''))
              ? __('This action is only available for the current active domain.', 'vaptsecure')
              : __('Forces sync with vapt-locked-config.php', 'vaptsecure'))
          ])
        ])
      ])
    ]);
  };



  const LicenseManager = ({ domains, fetchData, isSuper, loading, addDomain, deleteDomain, globalSetConfirmState, deletingId }) => {
    // Manage state for the selected domain (if multiple, allows switching)
    const [selectedDomainId, setSelectedDomainId] = useState(() => (Array.isArray(domains) && domains.length > 0) ? domains[0].id : null);

    // Derived current domain object
    const currentDomain = useMemo(() => {
      const doms = Array.isArray(domains) ? domains : [];
      // Use loose equality to handle string/number ID mismatches
      const found = doms.find(d => d.id == selectedDomainId);
      // console.log('LicenseManager Selection Debug:', { selectedDomainId, found, allDomains: doms });
      return found || (doms.length > 0 ? doms[0] : null);
    }, [domains, selectedDomainId]);

    // Local Form State

    const licenseUsage = useMemo(() => {
      const usage = {};
      (domains || []).forEach(d => {
        if (!d.license_id) return;
        if (!usage[d.license_id]) usage[d.license_id] = 0;
        if (!(d.is_enabled === '0' || d.is_enabled === false || d.is_enabled === 0)) {
          usage[d.license_id]++;
        }
      });
      return usage;
    }, [domains]);
    const formatDate = (dateString) => {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
      });
    };

    const [formState, setFormState] = useState({
      domain: '',
      is_wildcard: false,
      license_id: '',
      license_type: 'standard',
      manual_expiry_date: '',
      auto_renew: false,
      license_scope: 'single',
      installation_limit: 1
    });

    // New Domain Mode State
    const [isCreatingNew, setIsCreatingNew] = useState(false);

    // Sorting and Filtering state for the table
    const [sortBy, setSortBy] = useState('domain');
    const [sortOrder, setSortOrder] = useState('asc');
    const [searchQuery, setSearchQuery] = useState('');

    const sortedDomains = useMemo(() => {
      let doms = Array.isArray(domains) ? [...domains] : [];

      // Application of search filter
      if (searchQuery) {
        const query = searchQuery.toLowerCase();
        doms = doms.filter(d =>
          (d.domain || '').toLowerCase().includes(query) ||
          (d.license_id || '').toLowerCase().includes(query)
        );
      }

      // Sorting logic
      doms.sort((a, b) => {
        let valA = a[sortBy] || '';
        let valB = b[sortBy] || '';

        // Handle date sorting
        if (sortBy === 'first_activated_at' || sortBy === 'manual_expiry_date') {
          valA = valA ? new Date(valA).getTime() : 0;
          valB = valB ? new Date(valB).getTime() : 0;
        }

        if (typeof valA === 'string') {
          valA = valA.toLowerCase();
          valB = valB.toLowerCase();
        }

        if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
        return 0;
      });
      return doms;
    }, [domains, sortBy, sortOrder, searchQuery]);

    const [isSaving, setIsSaving] = useState(false);
    const [localStatus, setLocalStatus] = useState(null);
    const [confirmState, setConfirmState] = useState({ isOpen: false, type: null });

    // Sync form with current domain when selection changes or domain updates
    useEffect(() => {
      if (currentDomain && !isSaving && !loading) {
        const newDomain = currentDomain.domain || '';
        const newWildcard = !!parseInt(currentDomain.is_wildcard || 0);
        const newLicenseId = currentDomain.license_id || '';
        const newType = currentDomain.license_type || 'standard';
        const newExpiry = currentDomain.manual_expiry_date ? currentDomain.manual_expiry_date.split(' ')[0] : '';
        const newAuto = !!parseInt(currentDomain.auto_renew);
        const newScope = currentDomain.license_scope || 'single';
        const newLimit = parseInt(currentDomain.installation_limit) || 1;
        const newNotes = currentDomain.user_notes || '';

        // Only update if actually different to prevent flickering
        if (formState.domain !== newDomain ||
          formState.is_wildcard !== newWildcard ||
          formState.license_id !== newLicenseId ||
          formState.license_type !== newType ||
          formState.manual_expiry_date !== newExpiry ||
          formState.auto_renew !== newAuto ||
          formState.license_scope !== newScope ||
          formState.installation_limit !== newLimit ||
          formState.user_notes !== newNotes
        ) {
          setFormState({
            domain: newDomain,
            is_wildcard: newWildcard,
            license_id: newLicenseId,
            license_type: newType,
            manual_expiry_date: newExpiry,
            auto_renew: newAuto,
            license_scope: newScope,
            installation_limit: newLimit,
            user_notes: newNotes
          });
        }
      }
    }, [currentDomain, isSaving, loading]);
    const isDirty = (currentDomain || isCreatingNew) ? (
      isCreatingNew ||
      formState.domain !== (currentDomain.domain || '') ||
      formState.is_wildcard !== !!parseInt(currentDomain.is_wildcard || 0) ||
      formState.license_id !== (currentDomain.license_id || '') ||
      formState.license_type !== (currentDomain.license_type || 'standard') ||
      formState.manual_expiry_date !== (currentDomain.manual_expiry_date ? currentDomain.manual_expiry_date.split(' ')[0] : '') ||
      formState.auto_renew !== !!parseInt(currentDomain.auto_renew) ||
      formState.license_scope !== (currentDomain.license_scope || 'single') ||
      formState.installation_limit !== (parseInt(currentDomain.installation_limit) || 1) ||
      formState.user_notes !== (currentDomain.user_notes || '')
    ) : false;
    const isUniversalDomain = (formState.domain === '*') || ((formState.domain || '').indexOf('__universal__:') === 0);

    if (!currentDomain) {
      if (loading) {
        return el(PanelBody, { title: __('License & Subscription Management', 'vaptsecure'), initialOpen: true },
          el('div', { style: { padding: '30px', textAlign: 'center' } }, el('span', { className: 'components-spinner' }))
        );
      }
      return el(PanelBody, { title: __('License & Subscription Management', 'vaptsecure'), initialOpen: true },
        el('div', { style: { padding: '30px', textAlign: 'center' } }, [
          el('div', { style: { marginBottom: '20px', color: '#666' } }, __('No domains configured.', 'vaptsecure')),

          // Auto-Provision for Superadmins/Admins
          el('div', {
            style: {
              padding: '20px',
              background: '#f0f6fc',
              border: '1px solid #cce5ff',
              borderRadius: '8px',
              maxWidth: '500px',
              margin: '0 auto'
            }
          }, [
            el('h3', { style: { marginTop: 0 } }, __('Initialize Workspace License', 'vaptsecure')),
            el('p', null, sprintf(__('Detected environment: %s', 'vaptsecure'), window.location.hostname)),
            el('p', { style: { fontSize: '12px', color: '#666' } }, __('As a Superadmin, you can instantly provision a Developer License for this domain.', 'vaptsecure')),

            el(Button, {
              isPrimary: true,
              isBusy: isSaving,
              onClick: () => {
                setIsSaving(true);
                const hostname = window.location.hostname;
                // Calculate 100 years from now for Developer
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 36500);
                const expiry = tomorrow.toISOString().split('T')[0];

                apiFetch({
                  path: 'vaptsecure/v1/domains/update',
                  method: 'POST',
                  data: {
                    domain: hostname,
                    license_type: 'developer',
                    auto_renew: 1,
                    manual_expiry_date: expiry,
                    license_id: 'DEV-' + Math.random().toString(36).substr(2, 9).toUpperCase()
                  }
                }).then(() => {
                  setLocalStatus({ message: 'Domain Provisioned!', type: 'success' });
                  fetchData(); // Will trigger re-render with new domain
                }).catch(err => {
                  setIsSaving(false);
                  setLocalStatus({ message: 'Provision Failed: ' + err.message, type: 'error' });
                });
              }
            }, sprintf(__('Provision %s (Developer)', 'vaptsecure'), window.location.hostname)),

            localStatus && el('p', { style: { color: localStatus.type === 'error' ? 'red' : 'green', marginTop: '10px' } }, localStatus.message)
          ])
        ])
      );
    }

    const handleUpdate = (isManualRenew = false) => {
      setIsSaving(true);
      setLocalStatus({
        message: isManualRenew ? __('Performing Manual Renewal...', 'vaptsecure') : __('Updating License...', 'vaptsecure'),
        type: 'info'
      });

      let payload = {
        id: isCreatingNew ? undefined : currentDomain.id,
        domain: formState.domain,
        is_wildcard: formState.is_wildcard ? 1 : 0,
        license_id: formState.license_id,
        license_type: formState.license_type,
        manual_expiry_date: formState.manual_expiry_date,
        auto_renew: (formState.license_type === 'developer' || formState.auto_renew) ? 1 : 0,
        license_scope: formState.license_scope,
        installation_limit: formState.installation_limit,
        user_notes: formState.user_notes,
        action: isManualRenew ? 'manual_renew' : 'update'
      };

      // Manual Renew Logic
      if (isManualRenew) {
        const baseDateStr = currentDomain.manual_expiry_date || new Date().toISOString().split('T')[0];
        const parts = baseDateStr.split(' ')[0].split('-');
        // Create date in local time at 00:00:00 using parts
        const baseDate = new Date(parts[0], parts[1] - 1, parts[2]);

        let durationDays = 30;
        if (formState.license_type === 'pro') durationDays = 365;
        if (formState.license_type === 'developer' || formState.license_type === 'developer_unbound') durationDays = 36500; // ~100 years

        baseDate.setDate(baseDate.getDate() + durationDays);

        // Format back to YYYY-MM-DD manually to avoid UTC shift
        const y = baseDate.getFullYear();
        const m = String(baseDate.getMonth() + 1).padStart(2, '0');
        const d = String(baseDate.getDate()).padStart(2, '0');
        payload.manual_expiry_date = `${y} -${m} -${d} `;
        payload.renew_source = 'manual'; // Explicitly tag as manual
      }

      apiFetch({
        path: 'vaptsecure/v1/domains/update',
        method: 'POST',
        data: payload
      }).then(res => {
        if (res.success && res.domain) {
          setLocalStatus({ message: isCreatingNew ? __('Domain Registered!', 'vaptsecure') : __('License Updated!', 'vaptsecure'), type: 'success' });
          return fetchData().finally(() => {
            setIsSaving(false);
            if (isCreatingNew) {
              setIsCreatingNew(false);
              setSelectedDomainId(res.domain.id);
            }
            setTimeout(() => setLocalStatus(null), 3000);
          });
        }
        setIsSaving(false);
      }).catch(err => {
        setLocalStatus({ message: isCreatingNew ? __('Failed to register', 'vaptsecure') : __('Update Failed', 'vaptsecure'), type: 'error' });
        setIsSaving(false);
        setTimeout(() => setLocalStatus(null), 3000);
      });
    };

    const handleRollback = (type) => {
      setConfirmState({ isOpen: true, type });
    };

    const executeRollback = () => {
      const type = confirmState.type;
      setConfirmState({ isOpen: false, type: null });

      setIsSaving(true);
      setLocalStatus({ message: __('Reverting Renewals...', 'vaptsecure'), type: 'info' });

      apiFetch({
        path: 'vaptsecure/v1/domains/update',
        method: 'POST',
        data: {
          domain: currentDomain.domain,
          action: type
        }
      }).then(res => {
        if (res.success && res.domain) {
          setLocalStatus({ message: __('Rollback Successful!', 'vaptsecure'), type: 'success' });
          return fetchData();
        }
      }).catch(err => {
        setLocalStatus({ message: __('Rollback Failed', 'vaptsecure'), type: 'error' });
      }).finally(() => {
        setIsSaving(false);
        setTimeout(() => setLocalStatus(null), 3000);
      });
    };


    const toggleSort = (key) => {
      if (sortBy === key) {
        setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
      } else {
        setSortBy(key);
        setSortOrder('asc');
      }
    };

    const handleEdit = (domain) => {
      setSelectedDomainId(domain.id);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return el('div', { className: 'vapt-license-management-container' }, [
      // Section Header (No collapsible arrow)
      el('h2', { style: { padding: '16px 16px 10px', margin: 0, fontSize: '14px', fontWeight: 600, color: '#1e1e1e' } }, __('License & Subscription Management', 'vaptsecure')),
      // TOP: Two-Column Form Grid
      el('div', { className: 'vapt-license-grid' }, [
        // LEFT: Status Card
        el('div', { className: 'vapt-license-card' }, [
          el('div', { className: 'vapt-card-header-row', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' } }, [
            el('h3', { style: { margin: 0 } }, __('License Status', 'vaptsecure')),
            el('span', { className: `vapt-license-badge ${currentDomain.license_type || 'standard'}` },
              (currentDomain.license_type || 'Standard').toUpperCase()
            )
          ]),

          el('div', { className: 'vapt-info-row', style: { marginBottom: '15px' } }, [
            el(TextControl, {
              label: __('Domain Name', 'vaptsecure'),
              value: currentDomain.domain,
              readOnly: true,
              style: { background: '#f8fafc', color: '#64748b' }
            })
          ]),

          el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px', marginBottom: '15px' } }, [
            el(TextControl, {
              label: __('First Activated', 'vaptsecure'),
              value: currentDomain.first_activated_at ? formatDate(currentDomain.first_activated_at) : __('Not Activated', 'vaptsecure'),
              readOnly: true,
              style: { background: '#f8fafc', color: '#64748b' }
            }),
            el(TextControl, {
              label: __('Expiry Date', 'vaptsecure'),
              value: (currentDomain.license_type === 'developer')
                ? __('Never Expires', 'vaptsecure')
                : (currentDomain.manual_expiry_date ? formatDate(currentDomain.manual_expiry_date) : ''),
              readOnly: true,
              style: {
                background: '#f8fafc',
                color: (currentDomain.license_type !== 'developer' && currentDomain.manual_expiry_date && new Date(currentDomain.manual_expiry_date) < new Date()) ? '#dc2626' : '#64748b'
              }
            })
          ]),

          el('div', { className: 'components-base-control', style: { marginBottom: '15px' } }, [
            el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc', border: '1px solid #949494', borderRadius: '4px', padding: '0 12px', height: '40px' } }, [
              el('span', { style: { color: '#1e1e1e', fontSize: '13px', fontWeight: 500 } }, __('Terms Renewed', 'vaptsecure')),
              el('span', { style: { color: '#64748b', fontSize: '13px' } }, `${currentDomain.renewals_count || 0} Times`)
            ])
          ]),

          el('div', { className: 'vapt-desc-text' },
            (currentDomain.license_type === 'developer')
              ? __('Developer License: Perpetual access with no expiration.', 'vaptsecure')
              : (currentDomain.license_type === 'pro'
                ? __('Pro License: Annual renewal cycle with premium features.', 'vaptsecure')
                : __('Standard License: 30-day renewal cycle.', 'vaptsecure'))
          ),

          localStatus && el('div', {
            style: {
              marginTop: '15px',
              padding: '8px',
              borderRadius: '4px',
              background: localStatus.type === 'error' ? '#fde8e8' : '#def7ec',
              color: localStatus.type === 'error' ? '#9b1c1c' : '#03543f',
              fontSize: '12px', textAlign: 'center'
            }
          }, localStatus.message)
        ]),

        // RIGHT: Update Form
        el('div', { className: 'vapt-license-card' }, [
          el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '15px' } }, [
            el('div', { style: { flex: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
              el('h3', { style: { margin: 0 } }, isCreatingNew ? __('Register New Domain', 'vaptsecure') : __('Update License', 'vaptsecure')),
              (!isCreatingNew && el('div', { style: { display: 'flex', gap: '5px' } }, [
              el(Button, {
                isSmall: true,
                isSecondary: true,
                disabled: isDirty,
                onClick: () => {
                  setIsCreatingNew(true);
                  const baseDate = new Date();
                  baseDate.setDate(baseDate.getDate() + 30);
                  const defaultExpiry = baseDate.toISOString().split('T')[0];
                  setFormState({
                    domain: '', is_wildcard: false, license_id: '',
                    license_type: 'standard', manual_expiry_date: defaultExpiry,
                    auto_renew: false, license_scope: 'single', installation_limit: 1
                  });
                }
              }, __('+ Add New Domain', 'vaptsecure')),
              el(Button, {
                isSmall: true,
                isSecondary: true,
                disabled: isDirty,
                onClick: () => {
                  setIsCreatingNew(true);
                  const baseDate = new Date();
                  baseDate.setDate(baseDate.getDate() + 30);
                  const defaultExpiry = baseDate.toISOString().split('T')[0];
                  setFormState({
                    domain: `__universal__:${Math.random().toString(36).slice(2, 10).toUpperCase()}`,
                    is_wildcard: true,
                    license_id: '',
                    license_type: 'standard',
                    manual_expiry_date: defaultExpiry,
                    auto_renew: false,
                    license_scope: 'multisite',
                    installation_limit: 0
                  });
                }
              }, __('+ Add Universal', 'vaptsecure'))
            ])),
            ]),
            (!isCreatingNew && (Array.isArray(domains) && domains.length > 1) ? el('div', { style: { flex: 1, minWidth: '120px' } },
              el(SelectControl, {
                value: selectedDomainId,
                options: domains.map(d => ({
                  label: (d.domain === '*' || (d.domain || '').indexOf('__universal__:') === 0) ? __('Universal (Any Domain)', 'vaptsecure') : d.domain,
                  value: d.id
                })),
                onChange: (val) => { setSelectedDomainId(val); fetchData(undefined, true); },
                disabled: isSaving,
                style: { marginBottom: 0 }
              })
            ) : el('div', { style: { flex: 1, minWidth: '120px' } }))
          ]),

          el('div', { style: { display: 'flex', alignItems: 'flex-start', gap: '10px', marginBottom: '15px' } }, [
            el('div', { style: { flex: 2 } }, el(TextControl, {
              label: isCreatingNew ? __('New Domain Name', 'vaptsecure') : __('Domain Name (Rename)', 'vaptsecure'),
              value: formState.domain,
              disabled: isSaving || isUniversalDomain,
              placeholder: isUniversalDomain ? __('Universal — applies to any domain', 'vaptsecure') : '',
              onChange: (val) => setFormState({ ...formState, domain: val }),
              style: { marginBottom: 0, background: isUniversalDomain ? '#f1f5f9' : undefined },
              help: isUniversalDomain
                ? __('Universal license — not locked to any specific domain.', 'vaptsecure')
                : !isCreatingNew ? __('Warning: Changing this will rename the current domain.', 'vaptsecure') : ''
            })
            ),

            el('div', { style: { flex: 1, minWidth: '120px' } }, el(SelectControl, {
              label: __('Domain Type', 'vaptsecure'),
              value: isUniversalDomain ? '1' : (formState.is_wildcard ? '1' : '0'),
              options: [
                { label: __('Standard', 'vaptsecure'), value: '0' },
                { label: __('Wildcard', 'vaptsecure'), value: '1' }
              ],
              disabled: isSaving || isUniversalDomain,
              onChange: (val) => {
                setFormState({ ...formState, is_wildcard: val === '1' });
              },
              style: { marginBottom: 0 }
            }))
          ]),

          el('div', { style: { display: 'flex', gap: '15px', marginBottom: '15px', flexWrap: 'wrap' } }, [
            el('div', { style: { flex: 1 } }, el(SelectControl, {
              label: __('License Scope', 'vaptsecure'),
              value: isUniversalDomain ? 'multisite' : formState.license_scope,
              options: [
                { label: __('Single Domain', 'vaptsecure'), value: 'single' },
                { label: __('Multi-Site', 'vaptsecure'), value: 'multisite' }
              ],
              disabled: isSaving || isUniversalDomain,
              onChange: (val) => setFormState({ ...formState, license_scope: val }),
              style: { marginBottom: 0 }
            })),
            el('div', { style: { flex: '0 0 100px' } }, el(TextControl, {
              label: __('Limit', 'vaptsecure'),
              type: 'number',
              min: 0,
              disabled: isSaving || isUniversalDomain || formState.license_scope !== 'multisite',
              value: isUniversalDomain ? 0 : (formState.license_scope === 'multisite' ? formState.installation_limit : 1),
              onChange: (val) => setFormState({ ...formState, installation_limit: parseInt(val) || 0 }),
              help: (isUniversalDomain || (formState.license_scope === 'multisite' && formState.installation_limit === 0)) ? __('0 = Unlimited', 'vaptsecure') : '',
              style: { marginBottom: 0 }
            })),
            el('div', { style: { flex: 2 } }, el(TextControl, {
              label: __('LICENSE ID - Unique License Identifier', 'vaptsecure'),
              value: formState.license_id,
              disabled: true,
              readOnly: true,
              style: { background: '#f8fafc', color: '#64748b', marginBottom: 0 }
            }))
          ]),

          el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' } }, [
            el('div', null, [
              el(SelectControl, {
                label: __('License Type', 'vaptsecure'),
                value: formState.license_type,
                disabled: isSaving,
                options: [
                  { label: '7-Day Trial', value: '7-day-trial' },
                  { label: '15-Day Demo', value: '15-day-demo' },
                  { label: 'Standard (30 Days)', value: 'standard' },
                  { label: 'Pro (One Year)', value: 'pro' },
                  { label: 'Developer (Perpetual)', value: 'developer' }
                ],
                onChange: (val) => {
                  const baseDate = new Date();
                  let durationDays = 30;
                  if (val === 'pro') durationDays = 365;
                  if (val === 'developer') durationDays = 36500;
                  if (val === '7-day-trial') durationDays = 7;
                  if (val === '15-day-demo') durationDays = 15;

                  baseDate.setDate(baseDate.getDate() + durationDays);
                  const newExpiry = baseDate.toISOString().split('T')[0];

                  const isTrialDemo = val === '7-day-trial' || val === '15-day-demo';
                  setFormState({
                    ...formState,
                    license_type: val,
                    manual_expiry_date: newExpiry,
                    auto_renew: isTrialDemo ? false : formState.auto_renew // 🛡️ Force Auto Renew OFF for Trials/Demos
                  });
                }
              }),
              el('div', { style: { marginTop: '15px' } }, [
                el(ToggleControl, {
                  label: __('Auto Renew', 'vaptsecure'),
                  checked: (formState.license_type === 'developer') ? true : formState.auto_renew,
                  disabled: isSaving || formState.license_type === 'developer' || formState.license_type === '7-day-trial' || formState.license_type === '15-day-demo',
                  onChange: (val) => setFormState({ ...formState, auto_renew: val }),
                  help: (formState.license_type === '7-day-trial' || formState.license_type === '15-day-demo') 
                    ? __('Auto-renewal is unavailable for Trial/Demo licenses.', 'vaptsecure')
                    : __('Automatically extend expiry if active.', 'vaptsecure'),
                  className: `vapt-auto-renew-toggle ${formState.auto_renew ? 'is-enabled' : 'is-disabled'} ${formState.license_type === 'developer' ? 'is-perpetual' : ''} ${(formState.license_type === '7-day-trial' || formState.license_type === '15-day-demo') ? 'is-locked' : ''}`
                })
              ])
            ]),

            el('div', null, [
              (formState.license_type !== 'developer')
                ? el(TextControl, {
                  label: (formState.license_type === '7-day-trial' || formState.license_type === '15-day-demo') 
                    ? __('Expiry Status (Manual Only)', 'vaptsecure')
                    : __('New Expiry Date', 'vaptsecure'),
                  type: 'date',
                  value: formState.manual_expiry_date,
                  disabled: isSaving,
                  onChange: (val) => setFormState({ ...formState, manual_expiry_date: val })
                })
                : el(TextControl, {
                  label: __('Expiry Status', 'vaptsecure'),
                  value: __('Perpetual License', 'vaptsecure'),
                  readOnly: true,
                  disabled: true,
                  style: { background: '#f1f5f9', color: '#475569', fontStyle: 'italic' }
                }),
              
              el('div', { style: { marginTop: '15px' } }, [
                el(TextareaControl, {
                  label: __('User Notes', 'vaptsecure'),
                  value: formState.user_notes || '',
                  onChange: (val) => setFormState({ ...formState, user_notes: val }),
                  rows: 3,
                  placeholder: __('Add any private notes...', 'vaptsecure'),
                  disabled: isSaving
                })
              ])
            ])
          ]),



          el('div', { style: { display: 'flex', gap: '10px', marginTop: '20px', alignItems: 'center', flexWrap: 'wrap' } }, [
            el(Button, {
              isPrimary: true,
              isBusy: isSaving && !localStatus?.message.includes('Manual'),
              disabled: !isDirty || isSaving || !formState.domain,
              onClick: () => handleUpdate(false)
            }, isCreatingNew ? __('Register Domain & License', 'vaptsecure') : __('Update License', 'vaptsecure')),

            (isCreatingNew && el(Button, {
              isSecondary: true,
              disabled: isSaving,
              onClick: () => setIsCreatingNew(false)
            }, __('Cancel', 'vaptsecure'))),

            (!isCreatingNew && el(Button, {
              isSecondary: true,
              isBusy: isSaving && localStatus?.message.includes('Manual'),
              disabled: isUniversalDomain || formState.auto_renew || isSaving,
              onClick: () => handleUpdate(true)
            }, __('Manual Renew', 'vaptsecure'))),

            (!isCreatingNew && isDirty && el(Button, {
              isDestructive: false,
              variant: 'tertiary',
              disabled: isSaving,
              style: { marginLeft: '4px' },
              onClick: () => {
                setFormState({
                  domain: currentDomain.domain || '',
                  is_wildcard: currentDomain.is_wildcard == 1,
                  license_id: currentDomain.license_id || '',
                  license_type: currentDomain.license_type || 'standard',
                  manual_expiry_date: currentDomain.manual_expiry_date ? currentDomain.manual_expiry_date.split(' ')[0] : '',
                  auto_renew: currentDomain.auto_renew == 1,
                  license_scope: currentDomain.license_scope || 'single',
                  installation_limit: currentDomain.installation_limit || 1,
                  user_notes: currentDomain.user_notes || ''
                });
                setLocalStatus(null);
              }
            }, __('Cancel Edits', 'vaptsecure'))),

            (!isCreatingNew && currentDomain.renewals_count > 0) && el('div', { className: 'vapt-correction-controls' }, [
              el(Button, {
                className: 'is-link',
                onClick: () => handleRollback('undo')
              }, __('Undo Last', 'vaptsecure')),
              el(Button, {
                className: 'is-link is-destructive',
                onClick: () => handleRollback('reset')
              }, __('Reset Renewals', 'vaptsecure'))
            ])
          ])
        ])
      ]), // End Grid

      // BOTTOM: Domains List Table (Full Width)
      el('div', { className: 'vapt-license-table-wrap', style: { marginTop: '30px', width: '100%', borderTop: '1px solid #ddd', paddingTop: '30px' } }, [
        el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' } }, [
          el('h3', { style: { margin: 0 } }, __('Domain License Directory', 'vaptsecure')),
          el('div', { style: { width: '300px' } }, [
            el(TextControl, {
              placeholder: __('Search domains...', 'vaptsecure'),
              value: searchQuery,
              onChange: (val) => setSearchQuery(val),
              style: { marginBottom: 0 }
            })
          ])
        ]),
        el('table', { className: 'wp-list-table widefat fixed striped' }, [
          el('thead', null, el('tr', { style: { tableLayout: 'fixed' } }, [
            el('th', {
              className: `manage-column sortable ${sortBy === 'license_id' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('license_id'),
              style: { cursor: 'pointer', width: '160px', paddingRight: '10px' }
            }, [
              el('span', null, __('License ID', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'installation_limit' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('installation_limit'),
              style: { cursor: 'pointer', width: '140px', paddingRight: '10px' }
            }, [
              el('span', null, __('Usage', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'domain' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('domain'),
              style: { cursor: 'pointer', width: '200px', paddingRight: '10px' }
            }, [
              el('span', null, __('Domain', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column`,
              style: { width: 'auto', paddingRight: '10px' }
            }, [
              el('span', null, __('User Notes', 'vaptsecure'))
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'version' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('version'),
              style: { cursor: 'pointer', width: '90px', paddingRight: '10px' }
            }, [
              el('span', null, __('Version', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'license_type' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('license_type'),
              style: { cursor: 'pointer', width: '140px', paddingRight: '10px' }
            }, [
              el('span', null, __('License', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'first_activated_at' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('first_activated_at'),
              style: { cursor: 'pointer', width: '130px', paddingRight: '10px' }
            }, [
              el('span', null, __('Activated At', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', {
              className: `manage-column sortable ${sortBy === 'manual_expiry_date' ? 'sorted ' + sortOrder : ''}`,
              onClick: () => toggleSort('manual_expiry_date'),
              style: { cursor: 'pointer', width: '110px', paddingRight: '10px' }
            }, [
              el('span', null, __('Expiry', 'vaptsecure')),
              el('span', { className: 'sorting-indicator' })
            ]),
            el('th', { style: { width: '90px' } }, __('Renewals', 'vaptsecure')),
            el('th', { style: { width: '140px', textAlign: 'right' } }, __('Action', 'vaptsecure')),
          ])),
          el('tbody', null, sortedDomains.length === 0 ? el('tr', null, el('td', { colSpan: 10 }, __('No domains found.', 'vaptsecure'))) :
            sortedDomains.sort((a, b) => {
              if (sortBy === 'license_id') return sortOrder === 'asc' ? (a.license_id || '').localeCompare(b.license_id || '') : (b.license_id || '').localeCompare(a.license_id || '');
              if (sortBy === 'domain') return sortOrder === 'asc' ? a.domain.localeCompare(b.domain) : b.domain.localeCompare(a.domain);
              if (sortBy === 'license_type') return sortOrder === 'asc' ? (a.license_type || '').localeCompare(b.license_type || '') : (b.license_type || '').localeCompare(a.license_type || '');
              if (sortBy === 'installation_limit') return sortOrder === 'asc' ? (parseInt(a.installation_limit) || 1) - (parseInt(b.installation_limit) || 1) : (parseInt(b.installation_limit) || 1) - (parseInt(a.installation_limit) || 1);
              if (sortBy === 'first_activated_at') {
                if (!a.first_activated_at) return sortOrder === 'asc' ? 1 : -1;
                if (!b.first_activated_at) return sortOrder === 'asc' ? -1 : 1;
                if (a.first_activated_at < b.first_activated_at) return sortOrder === 'asc' ? -1 : 1;
                if (a.first_activated_at > b.first_activated_at) return sortOrder === 'asc' ? 1 : -1;
                return 0;
              }
              if (sortBy === 'manual_expiry_date') {
                if (!a.manual_expiry_date) return sortOrder === 'asc' ? 1 : -1;
                if (!b.manual_expiry_date) return sortOrder === 'asc' ? -1 : 1;
                if (a.manual_expiry_date < b.manual_expiry_date) return sortOrder === 'asc' ? -1 : 1;
                if (a.manual_expiry_date > b.manual_expiry_date) return sortOrder === 'asc' ? 1 : -1;
                return 0;
              }
              if (sortBy === 'version') {
                const vA = a.version || '1.0.0';
                const vB = b.version || '1.0.0';
                if (vA < vB) return sortOrder === 'asc' ? -1 : 1;
                if (vA > vB) return sortOrder === 'asc' ? 1 : -1;
                return 0;
              }
              return 0;
            }).map((dom) => el('tr', { key: dom.id, className: dom.id == selectedDomainId ? 'is-selected' : '' }, [
              el('td', { style: { width: '160px', paddingRight: '10px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, el('span', { style: { fontFamily: 'monospace', fontSize: '11px', color: '#64748b' } }, dom.license_id || '-')),
              el('td', { style: { width: '140px', paddingRight: '10px' } }, (dom.license_id) ? (() => {
                const count = licenseUsage[dom.license_id] || 0;
                if ((dom.license_type || '').toLowerCase() === 'developer_unbound') {
                  return el('div', { className: 'vapt-usage-directory-wrapper' }, [
                    el('span', { className: 'vapt-usage-count', style: { color: '#1e293b' } }, `${count}`),
                    el('span', { className: 'dashicons dashicons-admin-users vapt-usage-icon' })
                  ]);
                }
                const limit = parseInt(dom.installation_limit) || 1;
                const percent = Math.min(100, Math.round((count / limit) * 100));
                const status = percent >= 90 ? 'danger' : (percent >= 70 ? 'warning' : 'safe');
                return el('div', { className: 'vapt-usage-directory-wrapper' }, [
                  el('span', { className: 'vapt-usage-count', style: { color: percent >= 90 ? '#ef4444' : '#1e293b' } }, `${count} / ${limit}`),
                  limit > 1 && el('div', { className: 'vapt-usage-bar-bg', style: { flex: 1, margin: 0 } }, [
                    el('div', { className: `vapt-usage-bar-fill ${status}`, style: { width: `${percent}%` } })
                  ])
                ]);
              })() : '-'),
              el('td', { style: { width: '200px', paddingRight: '10px' } }, [
                el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } }, [
                  el('span', { className: 'dashicons dashicons-networking', style: { color: '#64748b', fontSize: '16px', width: '16px', height: '16px' } }),
                  el('strong', null, (dom.domain === '*' || (dom.domain || '').indexOf('__universal__:') === 0) ? 'Universal' : dom.domain),
                  (dom.domain === '*' || (dom.domain || '').indexOf('__universal__:') === 0) && el('span', { className: 'vapt-license-badge', style: { marginLeft: '4px', background: '#eef2ff', color: '#4338ca', border: '1px solid #e2e8f0', fontSize: '9px', padding: '1px 5px' } }, __('Universal', 'vaptsecure')),
                  (dom.is_wildcard == 1) && !((dom.domain === '*' || (dom.domain || '').indexOf('__universal__:') === 0)) && el('span', { className: 'vapt-license-badge', style: { marginLeft: '4px', background: '#f8fafc', color: '#64748b', border: '1px solid #e2e8f0', fontSize: '9px', padding: '1px 5px' } }, __('Wildcard', 'vaptsecure')),
                ])
              ]),
              el('td', { style: { width: 'auto', paddingRight: '10px' } }, el('div', { style: { fontSize: '12px', color: '#64748b', fontStyle: 'italic', maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }, title: dom.user_notes || '' }, dom.user_notes || '-')),
              el('td', { style: { fontWeight: '500', color: '#475569', paddingRight: '10px' } }, dom.version || '1.0.0'),
              el('td', { style: { paddingRight: '10px' } }, el('span', { className: `vapt-license-badge ${dom.license_type || 'standard'}` }, (dom.license_type || 'Standard').toUpperCase())),
              el('td', { style: { paddingRight: '10px' } }, dom.first_activated_at ? formatDate(dom.first_activated_at) : '-'),
              el('td', { style: { paddingRight: '10px' } }, (dom.license_type === 'developer') ? __('Never', 'vaptsecure') : (dom.manual_expiry_date ? formatDate(dom.manual_expiry_date) : '-')),
              el('td', { style: { paddingRight: '10px' } }, `${dom.renewals_count || 0}`),
              el('td', { style: { textAlign: 'right' } }, el('div', { style: { display: 'flex', gap: '8px', justifyContent: 'flex-end', alignItems: 'center' } }, [
                el('button', {
                  className: `vapt-status-pill-btn ${(dom.is_enabled === '0' || dom.is_enabled === 0 || dom.is_enabled === false) ? 'inactive' : 'active'}`,
                  style: (dom.is_enabled !== '0' && dom.is_enabled !== 0 && dom.is_enabled !== false) ? { background: '#dcfce7', color: '#15803d', borderColor: '#bbf7d0', padding: '4px 10px' } : { padding: '4px 10px' },
                  onClick: () => {
                    const nextState = (dom.is_enabled === '0' || dom.is_enabled === 0 || dom.is_enabled === false) ? 1 : 0;
                    addDomain(dom.domain, dom.is_wildcard, nextState, dom.id);
                  }
                }, [
                  el('span', { className: `dashicons dashicons-${(dom.is_enabled === '0' || dom.is_enabled === 0 || dom.is_enabled === false) ? 'no-alt' : 'yes'}`, style: { fontSize: '14px', marginRight: '4px' } }),
                  (dom.is_enabled === '0' || dom.is_enabled === 0 || dom.is_enabled === false) ? __('INACTIVE', 'vaptsecure') : __('ACTIVE', 'vaptsecure')
                ]),
                el(Tooltip, { text: __('Delete Domain License', 'vaptsecure') },
                  el('button', {
                    className: 'vapt-elite-action-btn delete',
                    style: { color: '#94a3b8', background: 'none', border: 'none', padding: 0 },
                    disabled: deletingId === dom.id,
                    onClick: () => {
                      globalSetConfirmState({
                        isOpen: true,
                        type: 'delete_license',
                        message: __('Are you sure you want to delete this domain license entirely?', 'vaptsecure'),
                        isDestructive: true,
                        onConfirm: () => { deleteDomain(dom.id); globalSetConfirmState(null); }
                      });
                    }
                  }, el('span', { className: `dashicons dashicons-${deletingId === dom.id ? 'update' : 'trash'}`, style: { fontSize: '18px' } }))
                ),
                el(Tooltip, { text: __('Invalidate License', 'vaptsecure') },
                  el('button', {
                    className: 'vapt-elite-action-btn',
                    style: { color: '#94a3b8', background: 'none', border: 'none', padding: 0 },
                    disabled: !dom.manual_expiry_date,
                    onClick: () => {
                      globalSetConfirmState({
                        isOpen: true,
                        type: 'invalidate_license',
                        message: __('Are you sure you want to invalidate this license? This will instantly trigger the kill-switch on the client side.', 'vaptsecure'),
                        isDestructive: true,
                        onConfirm: () => {
                          apiFetch({
                            path: 'vaptsecure/v1/domains/update',
                            method: 'POST',
                            data: { id: dom.id, action: 'invalidate' }
                          }).then(() => {
                            fetchData();
                            globalSetConfirmState(null);
                          });
                        }
                      });
                    }
                  }, el('span', { className: 'dashicons dashicons-lock', style: { fontSize: '18px' } }))
                )
              ]))
            ]))
          )
        ])
      ]),

      // Confirmation Modal
      el(VAPTSECURE_ConfirmModal, {
        isOpen: confirmState.isOpen,
        message: confirmState.type === 'undo'
          ? __('Are you sure you want to undo the last manual renewal?', 'vaptsecure')
          : __('Are you sure you want to reset all consecutive manual renewals?', 'vaptsecure'),
        onConfirm: executeRollback,
        onCancel: () => setConfirmState({ isOpen: false, type: null }),
        confirmLabel: __('Revert Now', 'vaptsecure'),
        isDestructive: confirmState.type === 'reset'
      })
    ]);
  };

  const resolvePath = window.VAPTSECURE_resolvePath || ((obj, path) => {
    if (!path) return undefined;
    return path.split('.').reduce((prev, curr) => (prev && prev[curr] !== undefined) ? prev[curr] : undefined, obj);
  });

  const getMappedContent = window.VAPTSECURE_getMappedContent || ((obj, mappingKey, fallbackKey, fieldMapping) => {
    if (fieldMapping && fieldMapping[mappingKey]) {
      const mapped = resolvePath(obj, fieldMapping[mappingKey]);
      if (mapped) return mapped;
    }
    return obj ? obj[fallbackKey] : undefined;
  });

  const generateDevInstructions = (f, fieldMapping = {}) => {
    if (!f) return '';

    const id = f.risk_id || f.key || 'N/A';
    const title = f.title || f.label || 'N/A';
    const severity = (typeof f.severity === 'object') ? (f.severity.level || 'Medium') : (f.severity || 'Medium');
    const priority = f.priority || 'Medium';

    // Mapped Fields Extraction for instructions
    const mappedDesc = getMappedContent(f, 'description', 'description', fieldMapping);
    const summary = (typeof mappedDesc === 'object' ? mappedDesc.summary : mappedDesc) || 'No core description available.';
    const mappedUiLayout = getMappedContent(f, 'ui_layout', 'ui_layout', fieldMapping);
    const mappedComponents = getMappedContent(f, 'components', 'components', fieldMapping);
    const mappedActions = getMappedContent(f, 'actions', 'actions', fieldMapping);
    const mappedAvailablePlatforms = getMappedContent(f, 'available_platforms', 'available_platforms', fieldMapping);
    const catalogPlatforms = Array.isArray(mappedAvailablePlatforms) ? mappedAvailablePlatforms : [];
    const normalizedCatalogPlatforms = catalogPlatforms
      .map(platform => String(platform || '').toLowerCase())
      .filter(Boolean);
    const catalogHas = (...terms) => normalizedCatalogPlatforms.some(platform => terms.some(term => platform.includes(term)));

    // Driver Detection (Skill Alignment)
    const targets = f.protection?.automated_protection?.implementation_targets || f.available_platforms || [];
    let detectedDriver = 'Manual / Hook (default)';
    let safetyRules = [];
    let targetFiles = [];
    let driverKey = '';

    const selection = f.active_enforcer;

    if (normalizedCatalogPlatforms.length > 0) {
      if (catalogHas('wp-config', 'wpconfig')) {
        detectedDriver = 'wp-config.php Constants';
        driverKey = 'wp_config';
        targetFiles = ['{ABSPATH}wp-config.php'];
        safetyRules = [
          'Always use `/* BEGIN VAPT {ID} */` and `/* END VAPT {ID} */` markers.',
          'Place constants BEFORE the `/* That\'s all, stop editing! */` line (before_wp_settings).',
          'Check if constant is already defined before defining it.',
          'Use correct boolean or string values as required by WP core.'
        ];
      }
      else if (catalogHas('php-functions', 'phpfunctions', 'wordpress core', 'wordpress', 'hook')) {
        detectedDriver = 'WordPress / PHP Hook';
        driverKey = 'php_functions';
        targetFiles = ['{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php'];
        safetyRules = [
          'Always use `// BEGIN VAPT {ID}` and `// END VAPT {ID}` markers.',
          'Use specific WordPress action or filter hooks.',
          'Prefix all functions with `vapt_` (e.g. `vapt_disable_xmlrpc`).',
          'Insert at the end of the file (`functions_php`).'
        ];
      }
      else if (catalogHas('nginx')) {
        detectedDriver = 'Nginx Conf';
        driverKey = 'nginx';
        targetFiles = ['/etc/nginx/conf.d/vapt-security.conf'];
        safetyRules = ['Use `# BEGIN VAPT {ID}` markers.', 'Ensure insertion is after `http {` loop.', 'Include `nginx -t` validation.'];
      }
      else if (catalogHas('cloudflare')) {
        detectedDriver = 'Cloudflare (Pattern 4)';
        driverKey = 'cloudflare';
        targetFiles = ['Cloudflare Dashboard / API via WAF Rules'];
      }
      else if (catalogHas('htaccess', 'apache', 'litespeed')) {
        detectedDriver = catalogHas('litespeed') ? 'LiteSpeed / .htaccess Compatibility' : '.htaccess (Apache Core)';
        driverKey = catalogHas('litespeed') ? 'litespeed' : 'htaccess';
        targetFiles = ['{ABSPATH}.htaccess'];
        safetyRules = [
          catalogHas('litespeed')
            ? 'Always use `# BEGIN VAPT LS RISK-XXX` and `# END VAPT LS RISK-XXX` markers for LiteSpeed-specific blocks.'
            : 'Always use `# BEGIN VAPT {ID}` and `# END VAPT {ID}` markers.',
          'Place RewriteRules BEFORE the `# BEGIN WordPress` block to ensure they execute.',
          'Use `[L,F]` for blocking rules.',
          'No forbidden directives (`TraceEnable`, `ServerSignature`, `<Directory>`).',
          'Wrap rewrites in `<IfModule mod_rewrite.c>` with `RewriteEngine On`.',
          ...(catalogHas('litespeed') ? ['Keep LiteSpeed guidance aligned with the `.htaccess` baseline and emit only LiteSpeed-compatible additions.'] : [])
        ];
      }
    } else if (selection) {
      const selLower = selection.toLowerCase();
      if (selLower.includes('htaccess') || selLower === 'apache' || selLower === 'litespeed') {
        detectedDriver = selLower === 'litespeed' ? 'LiteSpeed / .htaccess Compatibility' : '.htaccess (Apache Core)';
        driverKey = selLower === 'litespeed' ? 'litespeed' : 'htaccess';
        targetFiles = ['{ABSPATH}.htaccess'];
        safetyRules = [
          selLower === 'litespeed'
            ? 'Always use `# BEGIN VAPT LS RISK-XXX` and `# END VAPT LS RISK-XXX` markers for LiteSpeed-specific blocks.'
            : 'Always use `# BEGIN VAPT {ID}` and `# END VAPT {ID}` markers.',
          'Place RewriteRules BEFORE the `# BEGIN WordPress` block to ensure they execute.',
          'Use `[L,F]` for blocking rules.',
          'No forbidden directives (`TraceEnable`, `ServerSignature`, `<Directory>`).',
          'Wrap rewrites in `<IfModule mod_rewrite.c>` with `RewriteEngine On`.',
          ...(selLower === 'litespeed' ? ['Keep LiteSpeed guidance aligned with the `.htaccess` baseline and emit only LiteSpeed-compatible additions.'] : [])
        ];
      }
      else if (selLower.includes('wp-config')) {
        detectedDriver = 'wp-config.php Constants';
        driverKey = 'wp_config';
        targetFiles = ['{ABSPATH}wp-config.php'];
        safetyRules = [
          'Always use `/* BEGIN VAPT {ID} */` and `/* END VAPT {ID} */` markers.',
          'Place constants BEFORE the `/* That\'s all, stop editing! */` line (before_wp_settings).',
          'Check if constant is already defined before defining it.',
          'Use correct boolean or string values as required by WP core.'
        ];
      }
      else if (selLower.includes('functions') || selLower.includes('hook') || selLower === 'wordpress' || selLower === 'php') {
        detectedDriver = 'WordPress / PHP Hook';
        driverKey = 'php_functions';
        targetFiles = ['{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php'];
        safetyRules = [
          'Always use `// BEGIN VAPT {ID}` and `// END VAPT {ID}` markers.',
          'Use specific WordPress action or filter hooks.',
          'Prefix all functions with `vapt_` (e.g. `vapt_disable_xmlrpc`).',
          'Insert at the end of the file (`functions_php`).'
        ];
      }
      else if (selLower === 'nginx') {
        detectedDriver = 'Nginx Conf';
        driverKey = 'nginx';
        targetFiles = ['/etc/nginx/conf.d/vapt-security.conf'];
        safetyRules = ['Use `# BEGIN VAPT {ID}` markers.', 'Ensure insertion is after `http {` loop.', 'Include `nginx -t` validation.'];
      }
      else if (selLower === 'cloudflare') {
        detectedDriver = 'Cloudflare (Pattern 4)';
        driverKey = 'cloudflare';
        targetFiles = ['Cloudflare Dashboard / API via WAF Rules'];
      }
    } else if (targets.includes('.htaccess')) {
      detectedDriver = targets.includes('litespeed') ? 'LiteSpeed / .htaccess Compatibility' : '.htaccess (Apache Core)';
      driverKey = targets.includes('litespeed') ? 'litespeed' : 'htaccess';
      targetFiles = ['{ABSPATH}.htaccess'];
      safetyRules = [
        targets.includes('litespeed')
          ? 'Always use `# BEGIN VAPT LS RISK-XXX` and `# END VAPT LS RISK-XXX` markers for LiteSpeed-specific blocks.'
          : 'Always use `# BEGIN VAPT {ID}` and `# END VAPT {ID}` markers.',
        'Place RewriteRules BEFORE the `# BEGIN WordPress` block to ensure they execute.',
        'Use `[L,F]` for blocking rules.',
        'No forbidden directives (`TraceEnable`, `ServerSignature`, `<Directory>`).',
        'Wrap rewrites in `<IfModule mod_rewrite.c>` with `RewriteEngine On`.',
        ...(targets.includes('litespeed') ? ['Keep LiteSpeed guidance aligned with the `.htaccess` baseline and emit only LiteSpeed-compatible additions.'] : [])
      ];
    }
    else if (targets.includes('wp-config.php')) {
      detectedDriver = 'wp-config.php Constants';
      driverKey = 'wp_config';
      targetFiles = ['{ABSPATH}wp-config.php'];
      safetyRules = [
        'Always use `/* BEGIN VAPT {ID} */` and `/* END VAPT {ID} */` markers.',
        'Place constants BEFORE the `/* That\'s all, stop editing! */` line (before_wp_settings).',
        'Check if constant is already defined before defining it.',
        'Use correct boolean or string values as required by WP core.'
      ];
    }
    else if (targets.includes('PHP Hook') || targets.includes('WordPress') || targets.includes('PHP Functions') || targets.includes('WordPress Core')) {
      detectedDriver = 'WordPress / PHP Hook';
      driverKey = 'php_functions'; // Canonical key
      targetFiles = ['{ABSPATH}wp-content/plugins/vapt-protection-suite/vapt-functions.php'];
      safetyRules = [
        'Always use `// BEGIN VAPT {ID}` and `// END VAPT {ID}` markers.',
        'Use specific WordPress action or filter hooks.',
        'Prefix all functions with `vapt_` (e.g. `vapt_disable_xmlrpc`).',
        'Insert at the end of the file (`functions_php`).'
      ];
    }
    else if (targets.includes('Nginx')) {
      detectedDriver = 'Nginx Conf';
      driverKey = 'nginx';
      targetFiles = ['/etc/nginx/conf.d/vapt-security.conf'];
      safetyRules = ['Use `# BEGIN VAPT {ID}` markers.', 'Ensure insertion is after `http {` loop.', 'Include `nginx -t` validation.'];
    }
    else if (targets.includes('Cloudflare')) {
      detectedDriver = 'Cloudflare (Pattern 4)';
      targetFiles = ['Cloudflare Dashboard / API via WAF Rules'];
    }
    else if (targets.includes('litespeed')) {
      detectedDriver = 'LiteSpeed / .htaccess Compatibility';
      driverKey = 'litespeed';
      targetFiles = ['{ABSPATH}.htaccess'];
      safetyRules = [
        'Always use `# BEGIN VAPT LS RISK-XXX` and `# END VAPT LS RISK-XXX` markers for LiteSpeed-specific blocks.',
        'Treat `.htaccess` as the shared baseline and add only LiteSpeed-compatible enhancements.',
        'Avoid prohibited platform guidance; keep the brief WordPress-hosting scoped.'
      ];
    }

    const hasMappingRules = mappedUiLayout || mappedComponents || mappedActions;

    const lines = [
      `# VAPT Implementation Brief v2.0 (Schema-First)`,
      `**Risk**: ${id} (${title})`,
      `**Severity**: ${severity} | **Priority**: ${priority}`,
      ``,
      `## 0. Core Principle: Preserve Core Functionality`,
      `- **Whitelisting**: You MUST ensure that all security rules explicitly whitelist or preserve access to:`,
      `    - WordPress Admin (\`/wp-admin/\`, \`admin-ajax.php\`)`,
      `    - REST API Endpoints (\`/wp-json/\`)`,
      `    - JSON core endpoints.`,
      `- Failure to do this will result in a site lockout. Site availability is the top priority.`,
      ``,
      `## 🛡️ Strategic Mandate (Schema-First v2.0)`,
      `- **Goal**: ${summary}`,
      `- **Primary Driver**: ${detectedDriver}`,
      `- **Rulebook**: Ingest \`ai_agent_instructions_v2.0.json\`.`,
      `- **Contract**: Ingest \`vapt_platform_contract_v3.0.json\` and validate all contract gates before generation.`,
      `- **Blueprint**: Look up \`${id}\` in \`interface_schema_v2.0.json\`.`,
      `- **Pattern matching**: Map \`lib_key\` to \`enforcer_pattern_library_v2.0.json\`.`,
      ``,
      `## 🎚️ Toggle Intelligence`,
      `- **Control**: Check \`feat_enabled\` state for all enforcement code.`,
      `- **Conditional Logic**: Inject full blocks if enabled; comment out/remove if disabled.`,
      `- **Markers**: Always use VAPT markers (BEGIN/END) for all injections.`,
      `## 🧩 User Interface Requirements`,
      ...(hasMappingRules ? [
        `You MUST strictly adhere to the mapped UI Schema references provided in the Context data:`,
        ...(mappedUiLayout ? [`- **Layout**: Apply the exact section, order, and collapsible rules from the \`ui_layout\` object.`] : []),
        ...(mappedComponents ? [`- **Components**: Emit EXACTLY the arrays of components specified, replicating component IDs (\`UI-RISK-...-...\`) and handler names.`] : []),
        ...(mappedActions ? [`- **Actions**: Emit EXACTLY the listed actions, matching the REST endpoints and action IDs.`] : []),
        ...(mappedAvailablePlatforms ? [`- **Platforms**: Only render implementation components for platforms listed in \`available_platforms\`.`] : [])
      ] : [
        `- Adhere to standard UI component naming conventions (\`UI-RISK-{NNN}-{SEQ}\`).`,
        `- Construct standard form layouts per v2.0 guidelines.`
      ]),
      ``,
      `## 📁 Target Configuration Files`,
      `Ensure that the suggested protection configurations are specifically targeted to be written/appended within exactly these files:`,
      ...targetFiles.map(file => `- \`${file}\``),
      ``,
      `## ⚠️ Targeted Safety Guidelines`,
      ...(safetyRules.length > 0 ? safetyRules.map(rule => `- ${rule}`) : [`- Follow standard WordPress security best practices.`]),
      ``,
      `## 📋 Self-Check & Rubric (Completion Standards)`,
      `- **Develop Phase**: Minimum Score **18/19**.`,
      `- **Deploy Phase**: Minimum Score **18/19** (Governed by \`/develop-to-deploy\` workflow).`,
      ``,
      `> [!IMPORTANT]`,
      `> This brief follows the **VAPT platform contract** and the **Transition to Develop** sequence. You MUST prioritize **Whitelisting** and respect allowed platforms.`
    ];

    // Overlay custom user instructions if any existed previously
    if (f.dev_instruct && !f.dev_instruct.startsWith('# VAPT Implementation Brief')) {
      lines.push(``, `## 📝 Custom/Legacy Guidance`, f.dev_instruct);
    }

    return lines.join('\n');
  };

  const FeatureList = ({
    features, schema, updateFeature, loading, dataFiles, selectedFile, onSelectFile, onUpload, allFiles, hiddenFiles, onUpdateHiddenFiles, manageSourcesStatus, isManageModalOpen, setIsManageModalOpen, onRemoveFile, designPromptConfig, setDesignPromptConfig,
    historyFeature, setHistoryFeature, designFeature, setDesignFeature, transitioning, setTransitioning, isPromptConfigModalOpen, setIsPromptConfigModalOpen, isMappingModalOpen, setIsMappingModalOpen,
    sortBySource, setSortBySource, sortSourceDirection, setSortSourceDirection,
    environmentProfile
  }) => {
    const [confirmingFile, setConfirmingFile] = useState(null);
    const [columnOrder, setColumnOrder] = useState(() => {
      const saved = localStorage.getItem(`vaptsecure_col_order_${selectedFile}`);
      return saved ? JSON.parse(saved) : ['title', 'category', 'severity', 'description'];
    });

    const [visibleCols, setVisibleCols] = useState(() => {
      const saved = localStorage.getItem(`vaptsecure_visible_cols_${selectedFile}`);
      return saved ? JSON.parse(saved) : ['title', 'category', 'severity', 'description'];
    });

    // Update column defaults when schema changes if not already set
    useEffect(() => {
      const savedOrder = localStorage.getItem(`vaptsecure_col_order_${selectedFile}`);
      const savedVisible = localStorage.getItem(`vaptsecure_visible_cols_${selectedFile}`);

      vaptLog.log('Init Check:', { selectedFile, savedOrder: !!savedOrder, savedVisible: !!savedVisible });

      if (!savedOrder && schema?.item_fields) {
        vaptLog.log('Applying default order');
        setColumnOrder(['title', 'category', 'severity', 'description']);
      }
      if (!savedVisible && schema?.item_fields) {
        vaptLog.log('Applying default visibility');
        setVisibleCols(['title', 'category', 'severity', 'description']);
      }
    }, [schema, selectedFile]);

    // Effective columns to show in table
    const activeCols = columnOrder.filter(c => visibleCols.includes(c));

    useEffect(() => {
      localStorage.setItem(`vaptsecure_col_order_${selectedFile}`, JSON.stringify(columnOrder));
      localStorage.setItem(`vaptsecure_visible_cols_${selectedFile}`, JSON.stringify(visibleCols));
    }, [columnOrder, visibleCols, selectedFile]);

    const [filterStatus, setFilterStatus] = useState(() => localStorage.getItem('vaptsecure_filter_status') || 'all');
    const [selectedCategories, setSelectedCategories] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_selected_categories');
      return saved ? JSON.parse(saved) : [];
    });

    // Local Save Status for Columns
    const [colSaveStatus, setColSaveStatus] = useState(null);
    const isFirstMount = wp.element.useRef(true);

    useEffect(() => {
      if (isFirstMount.current) {
        isFirstMount.current = false;
        return;
      }
      localStorage.setItem(`vaptsecure_col_order_${selectedFile}`, JSON.stringify(columnOrder));
      localStorage.setItem(`vaptsecure_visible_cols_${selectedFile}`, JSON.stringify(visibleCols));
      setColSaveStatus('Saved');
      const timer = setTimeout(() => setColSaveStatus(null), 2000);
      return () => clearTimeout(timer);
    }, [columnOrder, visibleCols, selectedFile]);

    // Drag and Drop State
    const [draggedCol, setDraggedCol] = useState(null);

    const handleDragStart = (e, col) => {
      setDraggedCol(col);
      e.dataTransfer.effectAllowed = 'move';
      // e.target.style.opacity = '0.5'; 
    };

    const handleDragOver = (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    };

    const handleDrop = (e, targetCol) => {
      e.preventDefault();
      if (draggedCol === targetCol) return;

      const newOrder = [...columnOrder];
      const draggedIdx = newOrder.indexOf(draggedCol);
      const targetIdx = newOrder.indexOf(targetCol);

      newOrder.splice(draggedIdx, 1);
      newOrder.splice(targetIdx, 0, draggedCol);

      setColumnOrder(newOrder);
      setDraggedCol(null);
    };

    const [selectedSeverities, setSelectedSeverities] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_selected_severities');
      return saved ? JSON.parse(saved) : [];
    });
    const [sortBy, setSortBy] = useState(() => localStorage.getItem('vaptsecure_sort_by') || 'name');
    const [sortOrder, setSortOrder] = useState(() => localStorage.getItem('vaptsecure_sort_order') || 'asc');
    const [searchQuery, setSearchQuery] = useState(() => localStorage.getItem('vaptsecure_search_query') || '');
    const [fieldMapping, setFieldMapping] = useState({ test_method: '', verification_steps: '', verification_command: '', verification_expected: '', verification_engine: '' });


    // Load/Save Field Mapping per File
    useEffect(() => {
      if (!selectedFile) return;
      const saved = localStorage.getItem(`vaptsecure_field_mapping_${selectedFile}`);
      if (saved) {
        setFieldMapping(JSON.parse(saved));
      } else {
        setFieldMapping({ test_method: '', verification_steps: '', verification_command: '', verification_expected: '', verification_engine: '' });
      }
    }, [selectedFile]);

    useEffect(() => {
      if (!selectedFile) return;
      localStorage.setItem(`vaptsecure_field_mapping_${selectedFile}`, JSON.stringify(fieldMapping));
    }, [fieldMapping, selectedFile]);

    const toggleSort = (key) => {
      if (sortBy === key) {
        setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
      } else {
        setSortBy(key);
        setSortOrder('asc');
      }
    };

    // Persist filters
    useEffect(() => {
      localStorage.setItem('vaptsecure_filter_status', filterStatus);
      localStorage.setItem('vaptsecure_selected_categories', JSON.stringify(selectedCategories));
      localStorage.setItem('vaptsecure_selected_severities', JSON.stringify(selectedSeverities));
      localStorage.setItem('vaptsecure_sort_by', sortBy);
      localStorage.setItem('vaptsecure_sort_order', sortOrder);
      localStorage.setItem('vaptsecure_search_query', searchQuery);
    }, [filterStatus, selectedCategories, selectedSeverities, sortBy, sortOrder, searchQuery]);

    const [saveStatus, setSaveStatus] = useState(null); // Feedback for media/clipboard uploads

    // confirmTransition moved to VAPTAdmin to avoid modal flicker when state updates


    // Smart Toggle Handling
    const handleSmartToggle = (feature, toggleKey) => {
      const getNestedValue = (obj, path) => {
        return path?.split('.').reduce((acc, part) => acc && acc[part], obj);
      };

      const newVal = !feature[toggleKey];
      let updates = { [toggleKey]: newVal ? 1 : 0 }; // Ensure 1/0 for DB compatibility

      if (newVal) {
        let contentField = null;
        let mappingKey = null;

        if (toggleKey === 'include_test_method') {
          contentField = 'test_method'; mappingKey = 'test_method';
        } else if (toggleKey === 'include_verification') {
          contentField = 'verification_steps'; mappingKey = 'verification_steps';
        } else if (toggleKey === 'include_verification_engine') {
          contentField = 'generated_schema'; mappingKey = 'verification_engine';
        }

        if (contentField && mappingKey && fieldMapping[mappingKey]) {
          // Check if destination is effectively empty
          let isEmpty = !feature[contentField];
          if (Array.isArray(feature[contentField]) && feature[contentField].length === 0) isEmpty = true;
          if (typeof feature[contentField] === 'object' && feature[contentField] !== null && Object.keys(feature[contentField]).length === 0) isEmpty = true;
          // Special check for schema with empty controls
          if (contentField === 'generated_schema' && feature[contentField]?.controls?.length === 0) isEmpty = true;

          if (isEmpty) {
            const sourceKey = fieldMapping[mappingKey];
            let sourceVal = getNestedValue(feature, sourceKey);
            if (sourceVal) {
              if (contentField === 'generated_schema' && typeof sourceVal === 'string') {
                try { sourceVal = JSON.parse(sourceVal); } catch (e) {
                  vaptLog.warn('Failed to parse source JSON for mapping', e);
                }
              }
              updates[contentField] = sourceVal;
              vaptLog.log(`Smart Mapping populated ${contentField} from ${sourceKey} `);
            }
          }
        }
      }
      updateFeature(feature.key || feature.id, updates);
    };

    // 1. Analytics (Moved below filtering for scope)

    // 2. Extract Categories & Severities & All Keys
    const safeFeatures = Array.isArray(features) ? features : [];
    const categories = [...new Set(safeFeatures.map(f => f.category))].filter(Boolean).sort();
    const severities = [...new Set(safeFeatures.map(f => f.severity))].filter(Boolean);
    const severityOrder = ['critical', 'high', 'medium', 'low', 'informational'];
    const uniqueSeverities = [...new Set(severities.map(s => s.toLowerCase()))]
      .sort((a, b) => severityOrder.indexOf(a) - severityOrder.indexOf(b))
      .map(s => {
        const map = {
          'critical': 'Critical',
          'high': 'High',
          'medium': 'Medium',
          'low': 'Low',
          'informational': 'Informational'
        };
        return map[s] || (s.charAt(0).toUpperCase() + s.slice(1).toLowerCase());
      });

    // Collect all available keys from features data
    const allKeys = [...new Set(safeFeatures.reduce((acc, f) => [...acc, ...Object.keys(f)], []))].filter(k =>
      !['key', 'label', 'status', 'normalized_status', 'has_history', 'include_test_method', 'include_verification', 'include_verification_engine', 'wireframe_url', 'generated_schema', 'implemented_at', 'assigned_to'].includes(k)
    );
    if (!allKeys.includes('enforcer')) allKeys.push('enforcer');

    const resolveEnforcer = (feature) => {
      const platforms = feature.platform_implementations || {};

      // Fallback: If environment profile not loaded, use default capabilities
      const optimal = environmentProfile?.optimal_platform || 'php_functions';
      const capabilities = environmentProfile?.capabilities || {
        // Default capabilities when profile not loaded - enable all platforms
        php: true,
        apache: true,
        nginx: true,
        cloudflare_proxy: true,
        litespeed: true,
        wordpress: true,
        mod_rewrite: true,
        allowoverride: true
      };

      // Enhanced compatibility map with priority scores and platform mapping
      // Includes ALL platform names found in interface_schema_v2.0.json
      const compatibilityMap = {
        'apache_htaccess': {
          enforcers: ['.htaccess', 'Apache', 'Litespeed', 'LiteSpeed', 'OpenLiteSpeed'],
          priority: 90, // High priority - most effective
          requirements: ['mod_rewrite', 'AllowOverride']
        },
        'litespeed': {
          enforcers: ['Litespeed', 'LiteSpeed', 'OpenLiteSpeed'],
          priority: 88, // High priority - shared .htaccess baseline with LiteSpeed enhancements
          requirements: ['mod_rewrite', 'AllowOverride']
        },
        'nginx_config': {
          enforcers: ['Nginx', 'Nginx'],
          priority: 85, // High priority - excellent performance
          requirements: ['nginx.conf writable']
        },
        'php_functions': {
          enforcers: ['PHP Functions', 'WordPress', 'WordPress Core', 'wp-config.php'],
          priority: 70, // Lower priority - universal fallback
          requirements: ['PHP execution']
        },
        'cloudflare_edge': {
          enforcers: ['Cloudflare', 'Cloudflare'],
          priority: 100, // Highest priority - edge blocking
          requirements: ['api_token']
        },
        'server_cron': {
          enforcers: ['Server Cron', 'Server Cron'],
          priority: 60, // Low priority - background tasks only
          requirements: ['crontab access']
        }
      };

      // 1. Get ALL supported enforcers for this feature
      const availableEnforcers = new Set();

      // Add from platform_implementations (primary source)
      if (platforms && typeof platforms === 'object') {
        Object.keys(platforms).forEach(k => {
          if (k && typeof k === 'string') {
            availableEnforcers.add(k);
          }
        });
      }

      // Also check for enforcer in steps (legacy support)
      if (feature.steps && Array.isArray(feature.steps)) {
        feature.steps.forEach(s => {
          if (s.enforcer && typeof s.enforcer === 'string') {
            availableEnforcers.add(s.enforcer);
          }
        });
      }

      // Enhanced debug logging
      vaptLog.log(`Resolving enforcer for ${feature.key || feature.id}`, {
        capabilities,
        compatibilityMap,
        platformImplementations: feature.platform_implementations,
        availableEnforcers: Array.from(availableEnforcers),
        featureData: feature
      });

      if (availableEnforcers.size === 0) {
        vaptLog.warn(`No enforcers found for feature ${feature.key || feature.id}`);
      }

      // 2. Filter by environment compatibility (only if we have valid capabilities)
      // Define capability mapping outside the callback
      const capabilityToMap = {
        'php': 'php_functions',
        'apache': 'apache_htaccess',
        'nginx': 'nginx_config',
        'cloudflare_proxy': 'cloudflare_edge',
        'litespeed': 'litespeed',
        'wordpress': 'php_functions',
        'mod_rewrite': 'apache_htaccess',
        'allowoverride': 'apache_htaccess',
        // Add fallback mappings for all platform names
        'nginx_config': 'nginx_config',
        'apache_htaccess': 'apache_htaccess',
        'litespeed': 'litespeed',
        'php_functions': 'php_functions',
        'cloudflare_edge': 'cloudflare_edge',
        'server_cron': 'server_cron'
      };

      const compatibleEnforcers = Array.from(availableEnforcers).filter(enf => {
        // If no environment profile loaded, return ALL enforcers without filtering
        if (!environmentProfile) {
          return true;
        }

        const enfLower = enf.toLowerCase();
        return Object.entries(capabilities).some(([cap, enabled]) => {
          if (!enabled) return false;

          const mapKey = capabilityToMap[cap] || cap;
          const platformConfig = compatibilityMap[mapKey];
          if (!platformConfig) return false;
          const compatibleList = platformConfig.enforcers || [];
          return compatibleList.some(c => c.toLowerCase() === enfLower);
        });
      });

      vaptLog.log(`Compatible enforcers for ${feature.key || feature.id}:`, {
        compatibleEnforcers,
        environmentCapabilities: capabilities,
        compatibilityMapKeys: Object.keys(compatibilityMap)
      });

      // 3. Auto-select optimal enforcer if none is currently selected
      const currentEnforcer = feature.active_enforcer;
      vaptLog.log(`Current enforcer for ${feature.key || feature.id}:`, currentEnforcer);

      if (!currentEnforcer && compatibleEnforcers.length > 0) {
        // Try to match optimal platform first
        const optimalEnforcer = findOptimalEnforcer(compatibleEnforcers, optimal, compatibilityMap);
        vaptLog.log(`Optimal enforcer for ${feature.key || feature.id}:`, optimalEnforcer);

        if (optimalEnforcer) {
          // Auto-select the optimal enforcer
          return [optimalEnforcer];
        }

        // If no optimal match, select highest priority compatible enforcer
        const highestPriorityEnforcer = getHighestPriorityEnforcer(compatibleEnforcers, compatibilityMap);
        vaptLog.log(`Highest priority enforcer for ${feature.key || feature.id}:`, highestPriorityEnforcer);

        if (highestPriorityEnforcer) {
          return [highestPriorityEnforcer];
        }
      }

      const result = compatibleEnforcers;
      if (result.length === 0) {
        vaptLog.warn(`No compatible enforcer found for ${feature.key || feature.id}`);
      }
      vaptLog.log(`Returning enforcers for ${feature.key || feature.id}:`, result);
      return result;
    };

    /**
     * Find the enforcer that matches the optimal platform
     */
    const findOptimalEnforcer = (compatibleEnforcers, optimalPlatform, compatibilityMap) => {
      // Map optimal platform to capability names
      const platformToCapability = {
        'cloudflare_edge': 'cloudflare_edge',
        'nginx_config': 'nginx_config',
        'apache_htaccess': 'apache_htaccess',
        'litespeed': 'litespeed',
        'php_functions': 'php_functions',
        'server_cron': 'server_cron'
      };

      const targetCapability = platformToCapability[optimalPlatform];
      if (!targetCapability) return null;

      const platformConfig = compatibilityMap[targetCapability];
      if (!platformConfig) return null;

      // Find enforcer that matches the optimal platform's enforcers
      return compatibleEnforcers.find(enf => {
        const enfLower = enf.toLowerCase();
        return platformConfig.enforcers.some(e => e.toLowerCase() === enfLower);
      });
    };

    /**
     * Get the highest priority enforcer from compatible list
     */
    const getHighestPriorityEnforcer = (compatibleEnforcers, compatibilityMap) => {
      let highestPriority = -1;
      let selectedEnforcer = null;

      compatibleEnforcers.forEach(enf => {
        // Find which capability this enforcer belongs to
        for (const [cap, config] of Object.entries(compatibilityMap)) {
          if (config.enforcers && config.enforcers.some(e => e.toLowerCase() === enf.toLowerCase())) {
            if (config.priority > highestPriority) {
              highestPriority = config.priority;
              selectedEnforcer = enf;
            }
            break;
          }
        }
      });

      return selectedEnforcer;
    };

    // Update columnOrder if new keys are found that aren't in there
    useEffect(() => {
      const missingKeys = allKeys.filter(k => !columnOrder.includes(k));
      if (missingKeys.length > 0) {
        setColumnOrder([...columnOrder, ...missingKeys]);
      }
    }, [allKeys, columnOrder]);

    // 3. Filter & Sort
    let processedFeatures = [...safeFeatures];

    // Category Filter First
    if (selectedCategories.length > 0) {
      processedFeatures = processedFeatures.filter(f => selectedCategories.includes(f.category));
    }

    // Severity Filter (Case-Insensitive)
    if (selectedSeverities.length > 0) {
      const lowSelected = selectedSeverities.map(s => s.toLowerCase());
      processedFeatures = processedFeatures.filter(f => f.severity && lowSelected.includes(f.severity.toLowerCase()));
    }

    const stats = {
      unfilteredTotal: safeFeatures.length,
      total: processedFeatures.length,
      draft: processedFeatures.filter(f => f.status === 'Draft').length,
      nonDraft: processedFeatures.filter(f => !['draft', 'available', 'default', ''].includes(String(f.status || '').toLowerCase())).length,
      develop: processedFeatures.filter(f => f.status === 'Develop').length,
      release: processedFeatures.filter(f => f.status === 'Release').length
    };

    const resetFilters = () => {
      setSelectedCategories([]);
      setSelectedSeverities([]);
      setFilterStatus('all');
      setSearchQuery('');
    };

    // Status Filter Second
    if (filterStatus !== 'all') {
      processedFeatures = processedFeatures.filter(f => {
        // Handle legacy lowercase filters from localStorage
        const s = filterStatus.toLowerCase();
        const featureStatus = String(f.status || '').toLowerCase();
        if (s === 'non_draft') return !['draft', 'available', 'default', ''].includes(featureStatus);
        if (s === 'draft') return f.status === 'Draft';
        if (s === 'develop') return f.status === 'Develop';
        if (s === 'release') return f.status === 'Release';
        return f.status === filterStatus;
      });
    }

    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      processedFeatures = processedFeatures.filter(f =>
        (f.name || f.label).toLowerCase().includes(q) ||
        (f.description && f.description.toLowerCase().includes(q))
      );
    }

    processedFeatures.sort((a, b) => {
      // Primary Sort: Data Source
      if (sortBySource) {
        const getSourceWeight = (f) => {
          if (f.exists_in_multiple_files) return 3;
          if (f.is_from_active_file !== false) return 2;
          return 1;
        };
        const wA = getSourceWeight(a);
        const wB = getSourceWeight(b);
        if (wA !== wB) {
          return sortSourceDirection === 'asc' ? (wA - wB) : (wB - wA);
        }
      }

      // Secondary Sort: Column Headers (Existing Logic)
      const nameA = (a.name || a.label || '').toLowerCase();
      const nameB = (b.name || b.label || '').toLowerCase();
      const catA = (a.category || '').toLowerCase();
      const catB = (b.category || '').toLowerCase();

      const sevPriority = { 'critical': 4, 'high': 3, 'medium': 2, 'low': 1, 'informational': 0 };
      const sevA = sevPriority[(a.severity || '').toLowerCase()] || 0;
      const sevB = sevPriority[(b.severity || '').toLowerCase()] || 0;

      let comparison = 0;
      if (sortBy === 'name' || sortBy === 'title') comparison = nameA.localeCompare(nameB);
      else if (sortBy === 'category') comparison = catA.localeCompare(catB);
      else if (sortBy === 'severity') comparison = sevA - sevB;
      else if (sortBy === 'enforcer') {
        const enfA = (a.active_enforcer || (resolveEnforcer(a)[0] || '')).toLowerCase();
        const enfB = (b.active_enforcer || (resolveEnforcer(b)[0] || '')).toLowerCase();
        comparison = enfA.localeCompare(enfB);
      }

      else if (sortBy === 'status') {
        const priority = {
          'Release': 3,
          'Develop': 2,
          'Draft': 1
        };
        comparison = (priority[a.status] || 0) - (priority[b.status] || 0);
      } else {
        const valA = String(a[sortBy] !== undefined ? a[sortBy] : '').toLowerCase();
        const valB = String(b[sortBy] !== undefined ? b[sortBy] : '').toLowerCase();
        comparison = valA.localeCompare(valB, undefined, { numeric: true });
      }

      return sortOrder === 'asc' ? comparison : -comparison;
    });



    return el('div', { id: 'vapt-feature-list-tab', className: 'vapt-feature-list-tab-wrap' }, [
      el(PanelBody, { id: 'vapt-feature-list-panel', title: __('Exhaustive Feature List', 'vaptsecure'), className: 'vapt-compact-panel', initialOpen: true }, [
        // Top Controls & Unified Header
        el('div', { id: 'vapt-feature-list-header-controls', key: 'controls', style: { marginBottom: '10px' } }, [
          // Unified Header Block (Source, Columns, Manage, Upload)
          el('div', {
            id: 'vapt-feature-list-toolbar',
            className: 'vapt-toolbar-block'
          }, [
            // Branded Icon with Configure Columns Dropdown
            el(Dropdown, {
              renderToggle: ({ isOpen, onToggle }) => el('div', {
                id: 'vapt-btn-configure-columns',
                onClick: onToggle,
                className: 'vapt-toolbar-btn-icon',
                'aria-expanded': isOpen,
                title: __('Configure Table Columns', 'vaptsecure')
              }, el(Icon, { icon: 'layout', size: 18 })),
              renderContent: ({ onClose }) => {
                const activeFields = columnOrder.filter(c => visibleCols.includes(c) && allKeys.includes(c));
                const availableFields = allKeys.filter(c => !visibleCols.includes(c));
                const half = Math.ceil(availableFields.length / 2);
                const availableCol1 = availableFields.slice(0, half);
                const availableCol2 = availableFields.slice(half);

                return el('div', { style: { padding: '20px', width: '850px' } }, [
                  el('h4', { style: { marginTop: 0, marginBottom: '5px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' } }, [
                    sprintf(__('Configure Table Columns: %s', 'vaptsecure'), selectedFile),
                    el('div', { style: { display: 'flex', alignItems: 'center', gap: '15px' } }, [
                      colSaveStatus && el('span', { style: { fontSize: '11px', color: '#00a32a', fontWeight: 'bold' } }, __('Saved to Browser', 'vaptsecure')),
                      el(Button, {
                        isSecondary: true,
                        isSmall: true,
                        onClick: onClose,
                        style: { height: '24px', lineHeight: '1' }
                      }, __('Close', 'vaptsecure'))
                    ])
                  ]),
                  el('p', { style: { fontSize: '12px', color: '#666', marginBottom: '20px' } }, __('Confirm the table sequence and add/remove fields.', 'vaptsecure')),
                  el('div', { style: { display: 'grid', gridTemplateColumns: 'minmax(280px, 1.2fr) 1fr 1fr', gap: '15px' } }, [
                    el('div', null, [
                      el('h5', { style: { margin: '0 0 8px 0', fontSize: '11px', textTransform: 'uppercase', color: '#2271b1', fontWeight: 'bold' } }, __('Active Table Sequence', 'vaptsecure')),
                      el('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
                        activeFields.map((field, activeIdx) => {
                          const masterIdx = columnOrder.indexOf(field);
                          return el('div', {
                            key: field,
                            draggable: true,
                            onDragStart: (e) => handleDragStart(e, field),
                            onDragOver: handleDragOver,
                            onDrop: (e) => handleDrop(e, field),
                            style: {
                              display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                              padding: '4px 8px',
                              background: draggedCol === field ? '#eef' : '#f0f6fb',
                              borderRadius: '4px',
                              border: '1px solid #c8d7e1',
                              cursor: 'grab',
                              opacity: draggedCol === field ? 0.5 : 1,
                              transition: 'all 0.2s'
                            }
                          }, [
                            el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                              el('span', { className: 'dashicons dashicons-menu', style: { color: '#aaa', cursor: 'grab', fontSize: '16px' } }),
                              el('span', { style: { fontSize: '10px', fontWeight: 'bold', color: '#72777c', minWidth: '20px' } }, `#${activeIdx + 1}`),
                              el(CheckboxControl, {
                                label: field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' '),
                                checked: true,
                                onChange: () => setVisibleCols(visibleCols.filter(c => c !== field)),
                                __nextHasNoMarginBottom: true,
                                __next40pxDefaultSize: true,
                                style: { margin: 0 }
                              })
                            ])
                          ]);
                        })
                      )]),
                    el('div', null, [
                      el('h5', { style: { margin: '0 0 8px 0', fontSize: '11px', textTransform: 'uppercase', color: '#666', fontWeight: 'bold' } }, __('Available Fields', 'vaptsecure')),
                      el('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
                        availableCol1.map((field) => (
                          el('div', { key: field, style: { display: 'flex', alignItems: 'center', padding: '4px 8px', background: '#fff', borderRadius: '4px', border: '1px solid #e1e1e1' } }, [
                            el(CheckboxControl, {
                              label: field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' '),
                              checked: false,
                              onChange: () => setVisibleCols([...visibleCols, field]),
                              style: { margin: 0 }
                            })
                          ])
                        ))
                      )
                    ]),
                    el('div', null, [
                      el('h5', { style: { margin: '0 0 8px 0', fontSize: '11px', textTransform: 'uppercase', color: 'transparent', userSelect: 'none' } }, __('Available Fields', 'vaptsecure')),
                      el('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
                        availableCol2.map((field) => (
                          el('div', { key: field, style: { display: 'flex', alignItems: 'center', padding: '4px 8px', background: '#fff', borderRadius: '4px', border: '1px solid #e1e1e1' } }, [
                            el(CheckboxControl, {
                              label: field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, ' '),
                              checked: false,
                              onChange: () => setVisibleCols([...visibleCols, field]),
                              style: { margin: 0 }
                            })
                          ])
                        ))
                      )
                    ])
                  ]),
                  el('div', { style: { marginTop: '20px', borderTop: '1px solid #eee', paddingTop: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
                    el('span', { style: { fontSize: '11px', color: '#949494' } }, sprintf(__('%d Columns active, %d Available', 'vaptsecure'), activeFields.length, availableFields.length)),
                    el(Button, {
                      isLink: true, isDestructive: true,
                      onClick: () => {
                        const defaultFields = ['title', 'category', 'severity', 'description'];
                        setColumnOrder(defaultFields);
                        setVisibleCols(defaultFields);
                      }
                    }, __('Reset to Default', 'vaptsecure'))
                  ])
                ]);
              }
            }),

            // Map Include Fields Button
            el(Button, {
              isSecondary: true,
              isSmall: true,
              icon: 'networking', // Using networking to represent mapping
              onClick: () => setIsMappingModalOpen(true),
              style: { marginLeft: '5px', fontSize: '11px', height: '30px', minHeight: '30px', boxSizing: 'border-box', lineHeight: '1' }
            }, __('Map Include Fields', 'vaptsecure')),

            // Feature Source Selection
            // Feature Source Selection (Checkbox Style)
            el('div', {
              style: {
                flexGrow: 1,
                paddingLeft: '12px',
                display: 'flex',
                alignItems: 'center',
                gap: '12px'
              }
            }, [
              // Data Sources Label
              // el('span', { style: { fontWeight: '700', textTransform: 'uppercase', fontSize: '9px', color: '#64748b' } }, __('Data Sources:', 'vaptsecure')),

              // Checkbox Container
              el('div', { style: { display: 'flex', gap: '12px', flexWrap: 'wrap' } }, [
                // "All Data Files" Option (Only show for 3+ files)
                dataFiles.length >= 3 && el('label', {
                  key: 'all-files',
                  style: {
                    display: 'flex',
                    alignItems: 'center',
                    gap: '4px',
                    cursor: 'pointer',
                    fontSize: '11px',
                    fontWeight: (selectedFile || '').split(',').includes('__all__') ? '700' : '500',
                    color: (selectedFile || '').split(',').includes('__all__') ? '#1e3a8a' : '#64748b'
                  }
                }, [
                  el('input', {
                    type: 'checkbox',
                    checked: (selectedFile || '').split(',').includes('__all__'),
                    onChange: () => onSelectFile('__all__'),
                    style: { margin: 0, width: '13px', height: '13px' }
                  }),
                  __('All Data Files', 'vaptsecure')
                ]),
                // Individual Files
                ...dataFiles.map(file => {
                  const isAllSelected = (selectedFile || '').split(',').includes('__all__');
                  const currentFiles = (selectedFile || '').split(',').filter(f => f && f !== '__all__');
                  const isChecked = isAllSelected || currentFiles.includes(file.value);
                  const isLastSelected = isChecked && currentFiles.length === 1 && currentFiles.includes(file.value);
                  const isDisabled = isAllSelected || isLastSelected;

                  return el('label', {
                    key: file.value,
                    title: isDisabled ? (isLastSelected ? __('At least one source must be selected.', 'vaptsecure') : '') : '',
                    style: {
                      display: 'flex',
                      alignItems: 'center',
                      gap: '4px',
                      cursor: isDisabled ? 'default' : 'pointer',
                      fontSize: '11px',
                      fontWeight: isChecked ? '700' : '500',
                      color: isChecked ? '#1e3a8a' : '#64748b',
                      opacity: isDisabled ? 0.6 : 1
                    }
                  }, [
                    el('input', {
                      type: 'checkbox',
                      checked: isChecked,
                      disabled: isDisabled,
                      onChange: () => !isDisabled && onSelectFile(file.value),
                      style: {
                        margin: 0,
                        width: '13px',
                        height: '13px',
                        pointerEvents: isDisabled ? 'none' : 'auto',
                        cursor: isDisabled ? 'default' : 'pointer'
                      }
                    }),
                    file.label
                  ]);
                })
              ])
            ]),

            // Sort Control
            el('div', { style: { borderLeft: '1px solid #dcdcde', paddingLeft: '12px', display: 'flex', alignItems: 'center', gap: '8px', whiteSpace: 'nowrap' } }, [
              el(CheckboxControl, {
                label: __('Sort by Data Source', 'vaptsecure'),
                checked: sortBySource,
                onChange: (val) => setSortBySource(val),
                className: 'vapt-sort-checkbox',
                __nextHasNoMarginBottom: true,
                style: { margin: 0, whiteSpace: 'nowrap' } // Explicitly prevent wrap
              }),
              sortBySource && el(Button, {
                icon: sortSourceDirection === 'asc' ? 'arrow-up' : 'arrow-down',
                label: sortSourceDirection === 'asc' ? __('Ascending', 'vaptsecure') : __('Descending', 'vaptsecure'),
                onClick: () => setSortSourceDirection(sortSourceDirection === 'asc' ? 'desc' : 'asc'),
                style: { minWidth: '24px', padding: 0, height: '24px', marginLeft: '-4px' }
              })
            ]),

            // Manage Sources Trigger
            el('div', { style: { borderLeft: '1px solid #dcdcde', paddingLeft: '12px', display: 'flex', alignItems: 'center' } }, [
              el(Button, {
                isSecondary: true,
                icon: 'admin-settings',
                onClick: () => setIsManageModalOpen(true),
                label: __('Manage Sources', 'vaptsecure'),
                style: { height: '30px', minHeight: '30px', width: '30px', border: '1px solid #2271b1', color: '#2271b1', boxSizing: 'border-box', padding: 0 }
              })
            ]),

            // Upload Section
            el('div', { style: { borderLeft: '1px solid #dcdcde', paddingLeft: '12px', display: 'flex', flexDirection: 'column' } }, [
              // Label removed per user request
              el('input', {
                type: 'file',
                accept: '.json',
                onChange: (e) => e.target.files.length > 0 && onUpload(e.target.files[0]),
                style: { fontSize: '11px', color: '#555', height: '30px', padding: '4px 0', boxSizing: 'border-box' }
              })
            ])
          ]),

          // Manage Sources Modal
          isManageModalOpen && el(Modal, {
            title: __('Manage JSON Sources', 'vaptsecure'),
            onRequestClose: () => setIsManageModalOpen(false)
          }, [
            el('p', null, __('Deselect files to hide them from the Feature Source dropdown. The active file cannot be hidden.', 'vaptsecure')),
            el('div', { style: { maxHeight: '400px', overflowY: 'auto' } }, [
              allFiles.map(file => el('div', {
                key: file.filename,
                style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '5px 0', borderBottom: '1px solid #eee' }
              }, [
                el(CheckboxControl, {
                  label: file.display_name || file.filename.replace(/_/g, ' '),
                  checked: !hiddenFiles.includes(file.filename),
                  disabled: file.filename === selectedFile,
                  onChange: (val) => {
                    const newHidden = val
                      ? hiddenFiles.filter(h => h !== file.filename)
                      : [...hiddenFiles, file.filename];
                    onUpdateHiddenFiles(newHidden);
                  }
                }),
                el(Button, {
                  icon: 'no',
                  isDestructive: true,
                  isSmall: true,
                  disabled: file.filename === selectedFile,
                  onClick: () => setConfirmingFile(file.filename),
                  label: __('Remove from list', 'vaptsecure'),
                  style: { marginLeft: '10px' }
                })
              ]))
            ]),
            confirmingFile && el(Modal, {
              title: __('Confirm Removal', 'vaptsecure'),
              onRequestClose: () => setConfirmingFile(null),
              className: 'vapt-confirm-modal',
              overlayClassName: 'vapt-confirm-modal-overlay'
            }, [
              el('p', null, __('Are you sure you want to remove this source from the list? The physical file will remains on the server as a backup and can be restored by re-uploading.', 'vaptsecure')),
              el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px' } }, [
                el(Button, {
                  isSecondary: true,
                  onClick: () => setConfirmingFile(null)
                }, __('Cancel', 'vaptsecure')),
                el(Button, {
                  isPrimary: true,
                  isDestructive: true,
                  onClick: () => {
                    onRemoveFile(confirmingFile);
                    setConfirmingFile(null);
                  }
                }, __('Confirm Removal', 'vaptsecure'))
              ])
            ]),
            el('div', { style: { marginTop: '20px', textAlign: 'right', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '10px' } }, [
              manageSourcesStatus === 'saving' && el(Spinner),
              manageSourcesStatus === 'saved' && el('span', { style: { color: '#00a32a', fontWeight: 'bold' } }, __('Saved', 'vaptsecure')),
              el(Button, { isPrimary: true, onClick: () => setIsManageModalOpen(false) }, __('Close', 'vaptsecure'))
            ])
          ]),

          // Summary Pill Row
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
            el('span', { style: { fontWeight: '600', color: '#2271b1' } },
              stats.total === stats.unfilteredTotal
                ? sprintf(__('Total: %d', 'vaptsecure'), stats.total)
                : sprintf(__('Filtered: %d of %d', 'vaptsecure'), stats.total, stats.unfilteredTotal)
            ),
            el('span', { style: { opacity: 0.7 } }, sprintf(__('Draft: %d', 'vaptsecure'), stats.draft)),
            el('span', { style: { color: '#2271b1', fontWeight: '600' } }, sprintf(__('Non-Draft: %d', 'vaptsecure'), stats.nonDraft)),
            el('span', { style: { color: '#d63638', fontWeight: '600' } }, sprintf(__('Develop: %d', 'vaptsecure'), stats.develop)),
            el('span', { style: { color: '#46b450', fontWeight: '700' } }, sprintf(__('Release: %d', 'vaptsecure'), stats.release)),


            (stats.total < stats.unfilteredTotal || searchQuery || filterStatus !== 'all') && el(Button, {
              isLink: true,
              isSmall: true,
              onClick: resetFilters,
              style: { marginLeft: 'auto', fontSize: '10px', fontWeight: '600', textTransform: 'uppercase' }
            }, __('Reset All Filters', 'vaptsecure'))
          ])
        ]),
        // Filters Row (Ultra-Slim)
        el('div', { style: { display: 'flex', gap: '8px', flexWrap: 'nowrap', alignItems: 'stretch', marginBottom: '0' } }, [
          // Search Box
          el('div', { style: { flex: '1 1 180px', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666', letterSpacing: '0.02em' } }, __('Search Features', 'vaptsecure')),
            el('div', { style: { position: 'relative' } }, [
              el(TextControl, {
                value: searchQuery,
                onChange: setSearchQuery,
                placeholder: __('Search...', 'vaptsecure'),
                hideLabelFromVision: true,
                style: { margin: 0, height: '28px', minHeight: '28px', fontSize: '12px', paddingRight: '24px' }
              }),
              searchQuery && el(Button, {
                icon: 'no-alt', // Unfilled circle-style 'X'
                label: __('Clear Search', 'vaptsecure'),
                onClick: () => setSearchQuery(''),
                style: {
                  position: 'absolute',
                  right: '6px', // Slightly shifted for better balance
                  top: '50%',
                  transform: 'translateY(-50%)',
                  minWidth: '20px',
                  width: '20px',
                  height: '20px',
                  padding: 0,
                  color: '#717171', // Darker Grey
                  background: 'transparent',
                  boxShadow: 'none',
                  border: 'none',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  opacity: 0.8
                }
              })
            ])
          ]),

          // Category Unit
          el('div', { style: { flex: '0 0 auto', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center', minWidth: '150px' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666', letterSpacing: '0.02em' } }, __('Filter by Category', 'vaptsecure')),
            el(Dropdown, {
              renderToggle: ({ isOpen, onToggle }) => el(Button, {
                isSecondary: true,
                onClick: onToggle,
                'aria-expanded': isOpen,
                icon: 'filter',
                style: {
                  height: '28px',
                  minHeight: '28px',
                  width: '100%',
                  justifyContent: 'flex-start',
                  gap: '6px',
                  borderColor: '#2271b1',
                  color: '#2271b1',
                  background: '#fff',
                  fontSize: '11px',
                  padding: '0 8px'
                }
              }, selectedCategories.length === 0 ? __('All Categories', 'vaptsecure') : sprintf(__('%d Selected', 'vaptsecure'), selectedCategories.length)),
              renderContent: () => el('div', { style: { padding: '15px', minWidth: '250px', maxHeight: '300px', overflowY: 'auto' } }, [
                el(CheckboxControl, {
                  label: __('All Categories', 'vaptsecure'),
                  checked: selectedCategories.length === 0,
                  onChange: () => setSelectedCategories([])
                }),
                el('hr', { style: { margin: '10px 0' } }),
                ...categories.map(cat => el(CheckboxControl, {
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

          // Severity Unit
          el('div', { style: { flex: '1 1 auto', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666', letterSpacing: '0.02em' } }, __('Filter by Severity', 'vaptsecure')),
            el('div', { style: { display: 'flex', gap: '10px', flexWrap: 'wrap' } },
              uniqueSeverities.map(sev => el(CheckboxControl, {
                key: sev,
                label: sev,
                checked: selectedSeverities.some(s => s.toLowerCase() === sev.toLowerCase()),
                onChange: (val) => {
                  const lowSev = sev.toLowerCase();
                  if (val) setSelectedSeverities([...selectedSeverities, sev]);
                  else setSelectedSeverities(selectedSeverities.filter(s => s.toLowerCase() !== lowSev));
                },
                style: { margin: 0, fontSize: '11px' }
              }))
            )
          ]),

          // Lifecycle Unit
          el('div', { style: { flex: '1 1 auto', background: '#f6f7f7', padding: '4px 10px', borderRadius: '4px', border: '1px solid #dcdcde', display: 'flex', flexDirection: 'column', justifyContent: 'center' } }, [
            el('label', { className: 'components-base-control__label', style: { display: 'block', marginBottom: '2px', fontWeight: '600', textTransform: 'uppercase', fontSize: '9px', color: '#666', letterSpacing: '0.02em' } }, __('Filter by Lifecycle Status', 'vaptsecure')),
            el('div', { style: { display: 'flex', gap: '10px', flexWrap: 'wrap' } },
              [
                { label: __('All', 'vaptsecure'), value: 'all' },
                { label: __('Non-Draft', 'vaptsecure'), value: 'non_draft' },
                { label: __('Draft', 'vaptsecure'), value: 'draft' },
                { label: __('Develop', 'vaptsecure'), value: 'develop' },
                { label: __('Release', 'vaptsecure'), value: 'release' },
              ].map(opt => el('label', { key: opt.value, style: { display: 'flex', alignItems: 'center', gap: '4px', cursor: 'pointer', fontSize: '11px' } }, [
                el('input', {
                  type: 'radio',
                  name: 'vaptsecure_filter_status',
                  value: opt.value,
                  checked: filterStatus === opt.value,
                  onChange: (e) => setFilterStatus(e.target.value),
                  style: { margin: 0, width: '14px', height: '14px' }
                }),
                opt.label
              ])))
          ])
        ]),
      ]), // End Header PanelBody

      // 🛡️ SUPERADMIN: Visual Legend (v3.6.30)

      loading ? el(Spinner, { key: 'loader' }) : el('table', { id: 'vapt-main-feature-table', key: 'table', className: 'wp-list-table widefat striped vapt-feature-table' }, [
        el('thead', null, el('tr', null, [
          ...activeCols.map(col => {
            const label = col.charAt(0).toUpperCase() + col.slice(1).replace(/_/g, ' ');
            const isDescription = col === 'description';
            const width = isDescription ? 'auto' : '1%';
            const whiteSpace = isDescription ? 'normal' : 'nowrap';

            const isSortable = ['title', 'name', 'category', 'severity', 'enforcer'].includes(col) || col.toLowerCase().includes('risk');
            const isActive = sortBy === col || (col === 'title' && sortBy === 'name');

            return el('th', {
              id: `vapt-th-${col}`,
              key: col,
              onClick: isSortable ? () => toggleSort(col === 'title' ? 'name' : col) : null,
              className: `vapt-th-sortable ${isActive ? 'is-active' : ''} ${isSortable ? 'sortable' : ''}`,
              style: { width, whiteSpace }
            }, el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
              label,
              isSortable && el('span', {
                id: `vapt-sort-indicator-${col}`,
                className: 'vapt-sort-indicator',
                style: {
                  opacity: isActive ? 1 : 0.3,
                  color: isActive ? '#2271b1' : '#72777c',
                  display: 'flex',
                  alignItems: 'center'
                }
              }, el(Icon, {
                icon: isActive
                  ? (sortOrder === 'asc' ? 'arrow-up' : 'arrow-down')
                  : 'sort'
              }))
            ]));
          }),
          el('th', { style: { width: '1%', whiteSpace: 'nowrap' } }, __('Lifecycle Status', 'vaptsecure')),
          el('th', { style: { width: '1%', whiteSpace: 'nowrap' } }, __('Include', 'vaptsecure')),
        ])),
        el('tbody', null, processedFeatures.map((f) => el(Fragment, { key: f.key }, [
          el('tr', {
            className: f.exists_in_multiple_files ? 'vapt-feature-multi-file' : (f.is_from_active_file === false ? 'vapt-feature-inactive-only' : '')
          }, [
            ...activeCols.map(col => {
              let content = f[col] || '-';
              if (col === 'title' || col === 'label' || col === 'name') {
                content = el('strong', null, f.label || f.title || f.name);
              } else if (col === 'severity') {
                const s = (f[col] || '').toLowerCase();
                const map = { 'critical': 'Critical', 'high': 'High', 'medium': 'Medium', 'low': 'Low', 'informational': 'Informational' };
                const label = map[s] || (s.charAt(0).toUpperCase() + s.slice(1).toLowerCase());
                content = el('span', { className: `vapt-severity-text severity-${s}` }, label);
              } else if (col === 'implemented_at' && f[col]) {
                content = new Date(f[col]).toLocaleString();
              } else if (col === 'owasp') {
                content = el('span', { className: 'vapt-pill-compact', style: { background: '#f0f6fb', color: '#2271b1' } }, f[col]);
              } else if ((col === 'verification_steps' || col === 'verification') && Array.isArray(f[col])) {
                content = el('ul', { style: { margin: 0, padding: 0, listStyle: 'decimal inside', fontSize: '11px' } },
                  f[col].map((step, idx) => el('li', { key: idx, style: { marginBottom: '2px' } }, step))
                );
              } else if (Array.isArray(f[col])) {
                content = el('div', { style: { fontSize: '11px', display: 'flex', flexWrap: 'wrap', gap: '4px' } }, f[col].map((item, idx) => el('span', { key: idx, className: 'vapt-pill-compact' },
                  typeof item === 'object' ? JSON.stringify(item) : String(item)
                )));
              } else if (col === 'enforcer') {
                const choices = resolveEnforcer(f);
                const isAutoSelected = !f.active_enforcer && choices.length > 0;
                const current = f.active_enforcer || choices[0];

                if (choices.length === 0) {
                  content = el('span', { style: { color: '#949494', fontStyle: 'italic' } }, '-');
                } else if (choices.length === 1) {
                  content = el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
                    choices[0],
                    isAutoSelected && el('span', {
                      title: __('Auto-selected based on environment compatibility and priority', 'vaptsecure'),
                      style: {
                        fontSize: '10px',
                        color: '#2271b1',
                        background: '#e7f3ff',
                        padding: '1px 4px',
                        borderRadius: '3px',
                        fontWeight: '500'
                      }
                    }, __('AUTO', 'vaptsecure'))
                  ]);
                } else {
                  // Multiple choices - Radio Buttons with priority indicators
                  content = el('div', { className: 'vapt-enforcer-selector' }, choices.map(choice => {
                    const isSelected = current === choice;
                    const isAutoChoice = isAutoSelected && choice === choices[0];

                    return el('label', {
                      key: choice,
                      style: {
                        display: 'flex',
                        alignItems: 'center',
                        gap: '4px',
                        fontSize: '11px',
                        marginBottom: '2px',
                        cursor: 'pointer',
                        background: isAutoChoice ? '#e7f3ff' : 'transparent',
                        padding: '2px 4px',
                        borderRadius: '3px'
                      }
                    }, [
                      el('input', {
                        type: 'radio',
                        name: `enforcer-${f.key}`,
                        value: choice,
                        checked: isSelected,
                        onChange: () => updateFeature(f.key || f.id, { active_enforcer: choice }),
                        style: { margin: 0, width: '13px', height: '13px' }
                      }),
                      el('span', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
                        choice,
                        isAutoChoice && el('span', {
                          title: __('Auto-selected: Best match for your environment', 'vaptsecure'),
                          style: {
                            fontSize: '9px',
                            color: '#2271b1',
                            fontWeight: '600'
                          }
                        }, '★')
                      ])
                    ]);
                  }));
                }
              } else if (typeof f[col] === 'object' && f[col] !== null) {
                content = el('pre', { style: { fontSize: '10px', margin: 0, background: '#f0f0f0', padding: '4px', whiteSpace: 'pre-wrap' } }, JSON.stringify(f[col], null, 2));
              }
              return el('td', { key: col, style: { whiteSpace: col === 'description' ? 'normal' : 'nowrap' } }, content);
            }),
            el('td', { style: { verticalAlign: 'middle' } }, [
              el('div', { style: { display: 'flex', gap: '10px', alignItems: 'center' } }, [
                el(LifecycleIndicator, {
                  feature: f,
                  onDirectUpdate: (key, updates) => updateFeature(key, updates),
                  onChange: (newStatus) => {
                    // Validation: Prevent Draft -> Release
                    const currentStatus = f.status;
                    if (currentStatus === 'Draft' && newStatus === 'Release') {
                      setAlertState({
                        message: sprintf(__('Cannot transition directly from "Draft" to "%s". Please move to "Develop" first.', 'vaptsecure'), newStatus),
                        type: 'error'
                      });
                      return;
                    }

                    let defaultNote = '';
                    const title = f.label || f.title;
                    if (newStatus === 'Develop') {
                      defaultNote = `Initiating implementation for ${title}. Configuring workbench and internal security drivers.`;
                    } else if (newStatus === 'Release') {
                      defaultNote = `Verification protocol passed for ${title}. Ready for baseline deployment.`;
                    } else {
                      defaultNote = `Reverting ${title} to Draft for further planning.`;
                    }

                    setTransitioning({
                      ...f,
                      nextStatus: newStatus,
                      note: defaultNote,
                      remediation: f.remediation || '',
                      assurance: f.assurance || [],
                      assurance_against: f.assurance_against || [],
                      owasp: f.owasp || '',
                      test_method: f.test_method || '',
                      verification_steps: f.verification_steps || [],
                      tests: f.tests || [],
                      evidence: f.evidence || [],
                      schema_hints: f.schema_hints || {},
                      dev_instruct: newStatus === 'Develop' ? generateDevInstructions(f, fieldMapping) : ''
                    });
                  }
                }),
                el(Button, {
                  icon: 'backup',
                  isSmall: true,
                  isTertiary: true,
                  disabled: !f.has_history,
                  onClick: () => f.has_history && setHistoryFeature(f),
                  label: f.has_history ? __('View History', 'vaptsecure') : __('No History', 'vaptsecure'),
                  style: { marginLeft: '10px', opacity: f.has_history ? 1 : 0.4 }
                })
              ])
            ]),
            el('td', { className: 'vapt-support-cell', style: { verticalAlign: 'middle' } }, [
              el('div', { style: { display: 'flex', gap: '4px', alignItems: 'center', justifyContent: 'center', flexWrap: 'wrap' } }, [
                // Premium Button for Workbench Design Hub
                !['Draft', 'draft', 'available'].includes(f.status) && (() => {
                  const schema = typeof f.generated_schema === 'string' ? JSON.parse(f.generated_schema || '{}') : (f.generated_schema || {});
                  const isCustom = schema.controls && schema.controls.length > 0 && !schema._instructions;

                  // Determine status class
                  let stageClass = '';
                  if (f.status === 'Test' || f.status === 'test') {
                    stageClass = 'stage-test';
                  } else if (f.status === 'Release' || f.status === 'release') {
                    stageClass = 'stage-release';
                  }

                  return el('div', { className: 'vapt-flex-row', style: { gap: '8px' } }, [
                    el(Button, {
                      className: `vapt-premium-btn ${isCustom ? 'is-custom' : ''} ${stageClass}`,
                      onClick: (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        setDesignFeature(f);
                      },
                      title: isCustom ? __('Open Workbench Design Bench (Custom)', 'vaptsecure') : __('Open Workbench Design Bench (Default)', 'vaptsecure')
                    }, __('Workbench Design', 'vaptsecure')),
                    // A+ Adaptive Workbench Primary Action
                    el(Button, {
                      className: 'vapt-aplus-workbench-btn',
                      style: {
                        background: (() => {
                          // v1.9.5: 3-tier color matrix
                          // Orange → Release/implemented (approved for client)
                          // Green  → schema present AND enforced (actively deployed)
                          // Blue   → no schema or not enforced (pending implementation)
                          const isRelease = ['release', 'Release', 'implemented'].includes(f.status);
                          const hasSchema = f.generated_schema &&
                            f.generated_schema !== 'null' &&
                            f.generated_schema !== '{}' &&
                            f.generated_schema !== '[]' &&
                            (Array.isArray(f.generated_schema) ? f.generated_schema.length > 0 : Object.keys(f.generated_schema).length > 0);
                          const isEnforced = (f.is_enforced == 1) && hasSchema;
                          if (isRelease) return 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)'; // Orange
                          if (isEnforced) return 'linear-gradient(135deg, #10b981 0%, #059669 100%)'; // Green
                          return 'linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%)';                 // Blue
                        })(),
                        color: '#fff',
                        border: 'none',
                        fontWeight: '600',
                        fontSize: '11px',
                        padding: '0px 10px',
                        borderRadius: '4px',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                      },
                      onClick: (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        if (window.VAPTSECURE_APlusGenerator) {
                          const aplusSchema = window.VAPTSECURE_APlusGenerator.generate(f);
                          setDesignFeature({ ...f, generated_schema: aplusSchema, is_adaptive_deployment: 1 });
                        }
                      },
                      title: __('Initiate A+ Adaptive Schema Workflow (v3.2)', 'vaptsecure')
                    }, __('A+ Workbench', 'vaptsecure'))
                  ]);
                })()
              ])
            ])
          ])
        ])))
      ])
    ]);
  };

  const VAPTAdmin = () => {
    const [features, setFeatures] = useState([]);
    const [schema, setSchema] = useState({ item_fields: [] });
    const [domains, setDomains] = useState([]);
    const [dataFiles, setDataFiles] = useState([]);
    const [selectedFile, setSelectedFile] = useState('interface_schema_v2.0.json');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isDomainModalOpen, setDomainModalOpen] = useState(false);
    const [selectedDomain, setSelectedDomain] = useState(null);
    const [saveStatus, setSaveStatus] = useState(null); // { message: '', type: 'info'|'success'|'error' }
    const [designPromptConfig, setDesignPromptConfig] = useState(null);
    const [isPromptConfigModalOpen, setIsPromptConfigModalOpen] = useState(false);
    const [isMappingModalOpen, setIsMappingModalOpen] = useState(false);
    const [transitioning, setTransitioning] = useState(null);
    const [activeTab, setActiveTab] = useState(() => localStorage.getItem('vaptsecure_admin_active_tab') || 'features');
    const [historyFeature, setHistoryFeature] = useState(null);
    const [designFeature, setDesignFeature] = useState(null);
    const [confirmState, setConfirmState] = useState(null);
    const [selectedDomains, setSelectedDomains] = useState([]);
    const [alertState, setAlertState] = useState(null);
    const [rootAiInstructions, setRootAiInstructions] = useState({});
    const [rootGlobalSettings, setRootGlobalSettings] = useState({});
    // v1.9.2 – Batch Revert state
    const [batchRevertModal, setBatchRevertModal] = useState(null); // null | { previewData, isLoading, isExecuting }
    const [includeBroken, setIncludeBroken] = useState(true); // Toggle for including broken features (default: true)
    const [includeRelease, setIncludeRelease] = useState(false); // Toggle for including Release features

    const [catalogInfo, setCatalogInfo] = useState({ file: '', count: 0 }); // v3.6.29
    const [sortBySource, setSortBySource] = useState(false); // Primary Sort
    const [sortSourceDirection, setSortSourceDirection] = useState('desc'); // Primary Sort Direction
    const [environmentProfile, setEnvironmentProfile] = useState(null); // v2.4.14 - For dynamic enforcer mapping

    // Field Mapping State
    const [fieldMapping, setFieldMapping] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_field_mapping');
      return saved ? JSON.parse(saved) : {};
    });

    useEffect(() => {
      localStorage.setItem('vaptsecure_field_mapping', JSON.stringify(fieldMapping));
    }, [fieldMapping]);

    const allKeys = useMemo(() => {
      // Collect keys from multiple data sources
      const sources = [];

      // 1. Features data (existing source)
      if (features && features.length > 0) {
        sources.push(...features);
      }

      // 2. AI Agent Instructions (contains patterns, verification steps, etc.)
      if (rootAiInstructions && Object.keys(rootAiInstructions).length > 0) {
        sources.push(rootAiInstructions);
      }

      // 3. Global Settings (may contain operational context, verification steps)
      if (rootGlobalSettings && Object.keys(rootGlobalSettings).length > 0) {
        sources.push(rootGlobalSettings);
      }

      if (sources.length === 0) return [];

      const flattenKeys = (obj, prefix = '', depth = 0) => {
        let keys = [];
        // Increase depth limit to 3 to capture deeper nested keys
        // This allows us to get keys like patterns.RISK-001.wp_config.verification
        if (depth > 3) return keys;
        for (const key in obj) {
          if (!obj.hasOwnProperty(key)) continue;
          const newKey = prefix ? `${prefix}.${key}` : key;

          if (typeof obj[key] === 'object' && obj[key] !== null && !Array.isArray(obj[key])) {
            const childKeys = flattenKeys(obj[key], newKey, depth + 1);
            // Include child keys
            if (childKeys.length > 0) keys = keys.concat(childKeys);
            // Also include the object key itself if it might be used directly
            // But skip very generic keys like 'patterns' unless they have specific value
            if (depth < 2 || !['patterns', 'enforcer_key_map', 'bundle_files'].includes(key)) {
              keys.push(newKey);
            }
          } else {
            // For leaf values, include the key
            keys.push(newKey);
          }
        }
        return keys;
      };

      const keys = new Set();
      sources.forEach(source => {
        const flat = flattenKeys(source);
        flat.forEach(k => keys.add(k));
      });

      // Add some common variations that might not be in the data
      const additionalKeys = [
        'operational_notes.context',
        'testing.verification_steps',
        'verification_steps',
        'context',
        'steps',
        'verification',
        'operational_context',
        'implementation_context'
      ];
      additionalKeys.forEach(k => keys.add(k));

      return Array.from(keys).sort();
    }, [features, rootAiInstructions, rootGlobalSettings]);

    // Status Auto-clear helper
    useEffect(() => {
      if (saveStatus && saveStatus.type === 'success') {
        const timer = setTimeout(() => setSaveStatus(null), 2000);
        return () => clearTimeout(timer);
      }
    }, [saveStatus]);

    const fetchData = (file = selectedFile, silent = false) => {
      vaptLog.log('Fetching data for file:', file);
      if (!silent) setLoading(true);
      setSchema({ item_fields: [] }); // Clear previous schema while loading

      // Use individual catches to prevent one failure from blocking all
      const fetchFeatures = apiFetch({ path: `vaptsecure/v1/features?file=${file}` })
        .then(res => {
          if (res.error) throw new Error(res.error);
          setFeatures(res.features || []);
          setSchema(res.schema || { item_fields: [] });
          setDesignPromptConfig(res.design_prompt || null); // Load prompt config
          setRootAiInstructions(res.ai_agent_instructions || {});
          setRootGlobalSettings(res.global_settings || {});
          setEnvironmentProfile(res.environment_profile || null);
          if (res.active_catalog) {
            setCatalogInfo({ file: res.active_catalog, count: res.total_features || 0 });
          }
          return res;
        })
        .catch(err => { vaptLog.error('Features fetch error:', err); return []; });
      const fetchDomains = apiFetch({ path: 'vaptsecure/v1/domains' })
        .catch(err => { vaptLog.error('Domains fetch error:', err); return []; });
      const fetchDataFiles = apiFetch({ path: 'vaptsecure/v1/data-files' })
        .catch(err => { vaptLog.error('Data files fetch error:', err); return []; });

      return Promise.all([fetchFeatures, fetchDomains, fetchDataFiles])
        .then(([res, domainData, files]) => {
          const cleanedFiles = (files || []).map(f => ({ ...f, label: (f.label || f.filename).replace(/_/g, ' ') }));
          setFeatures(res.features || []);
          setSchema(res.schema || { item_fields: [] });
          setDesignPromptConfig(res.design_prompt || null);
          setRootAiInstructions(res.ai_agent_instructions || {});
          setRootGlobalSettings(res.global_settings || {});
          setEnvironmentProfile(res.environment_profile || null);
          setDomains(domainData || []);
          setDataFiles(cleanedFiles);
          setLoading(false);
        })
        .catch((err) => {
          vaptLog.error('Dashboard data fetch error:', err);
          setError(sprintf(__('Critical error loading dashboard data: %s', 'vaptsecure'), err.message || 'Unknown error'));
          setLoading(false);
        });
    };

    useEffect(() => {
      // First fetch the active file from backend setup
      apiFetch({ path: 'vaptsecure/v1/active-file' }).then(res => {
        if (res.active_file) {
          setSelectedFile(res.active_file);
          fetchData(res.active_file);
        } else {
          fetchData();
        }
      }).catch(() => fetchData());
    }, []);

    const onSelectFile = (file) => {
      const BASELINE_FILE = 'interface_schema_v2.0.json';
      let nextFiles = [];
      const currentFiles = (selectedFile || '').split(',').filter(Boolean);

      if (file === '__all__') {
        nextFiles = ['__all__'];
      } else {
        const realFiles = currentFiles.filter(f => f !== '__all__');

        if (currentFiles.includes(file)) {
          // Deselect this file
          // Prevent deselecting if it is the last remaining file
          if (realFiles.length <= 1) return;

          nextFiles = currentFiles.filter(f => f !== file && f !== '__all__');
        } else {
          // Add this file to selection
          nextFiles = [...realFiles, file];
        }
      }

      const nextFileStr = nextFiles.join(',') || 'interface_schema_v2.0.json'; // Fallback to default if empty
      setSelectedFile(nextFileStr);
      fetchData(nextFileStr);
      // Persist to backend
      apiFetch({
        path: 'vaptsecure/v1/active-file',
        method: 'POST',
        data: { file: nextFileStr }
      }).catch(err => vaptLog.error('Failed to sync active file:', err));
    };

    const updateFeature = (key, data) => {
      // Optimistic Update
      setFeatures(prev => prev.map(f => f.key === key ? { ...f, ...data } : f));
      setSaveStatus({ message: __('Saving...', 'vaptsecure'), type: 'info' });

      return apiFetch({
        path: 'vaptsecure/v1/features/update',
        method: 'POST',
        data: { key, ...data }
      }).then(() => {
        setSaveStatus({ message: __('Saved', 'vaptsecure'), type: 'success' });
      }).catch(err => {
        vaptLog.error('Update failed:', err);
        const errMsg = err.message || (err.data && err.data.message) || err.error || __('Error saving!', 'vaptsecure');
        setSaveStatus({ message: errMsg, type: 'error' });
      });
    };

    const confirmTransition = (formValues) => {
      if (!transitioning) return;
      const { key, nextStatus } = transitioning;
      const { note, dev_instruct, wireframeUrl } = formValues;

      const safeFeatures = Array.isArray(features) ? features : [];
      const feature = safeFeatures.find(f => f.key === key);
      let updates = { status: nextStatus, history_note: note, dev_instruct: dev_instruct };

      // Save Wireframe if provided
      if (wireframeUrl) {
        updates.wireframe_url = wireframeUrl;
      }

      // Special Case: Reset if moving back to Draft
      if (nextStatus === 'Draft' || nextStatus === 'draft') {
        updates.generated_schema = null;
        updates.implementation_data = null;
        updates.has_history = false;
        updates.wireframe_url = ''; // Clear wireframe too
        updates.dev_instruct = '';
        updates.include_verification_engine = 0;
        updates.include_verification_guidance = 0;
        // No need to set reset_history flag here because the backend handles the actual deletion based on status=Draft
        // But we update optimistic state above (has_history=false)
      }

      // Auto-Generate Interface when moving to 'Develop' (Phase 6 transition)
      if (nextStatus === 'Develop' && typeof Generator !== 'undefined' && Generator && feature && feature.remediation) {
        try {
          const schema = Generator.generate(feature.remediation, dev_instruct);
          if (schema) {
            updates.generated_schema = schema;
            vaptLog.log('Auto-generated schema for ' + key, schema);
          }
        } catch (e) {
          vaptLog.error('Generation error', e);
        }
      }

      updateFeature(key, updates);
      setTransitioning(null);
    };

    const addDomain = (domain, isWildcard = false, isEnabled = true, id = null) => {
      // Optimistic Update for better UX
      if (id) {
        setDomains(prev => prev.map(d => d.id === id ? { ...d, domain, is_wildcard: isWildcard, is_enabled: isEnabled } : d));
      }

      // Explicitly pass values as booleans to avoid truthiness confusion on backend
      return apiFetch({
        path: 'vaptsecure/v1/domains/update',
        method: 'POST',
        data: {
          id: id,
          domain,
          is_wildcard: Boolean(isWildcard),
          is_enabled: Boolean(isEnabled)
        }
      }).then((res) => {
        if (res.domain) {
          setDomains(prev => {
            const exists = prev.find(d => d.id === res.domain.id);
            if (exists) {
              return prev.map(d => d.id === res.domain.id ? res.domain : d);
            } else {
              return [...prev, res.domain];
            }
          });
        }
        setSaveStatus({ message: __('Domain updated successfully', 'vaptsecure'), type: 'success' });
        fetchData();
        return res;
      }).catch(err => {
        setSaveStatus({ message: __('Failed to update domain', 'vaptsecure'), type: 'error' });
        fetchData(); // Rollback to server state
        throw err;
      });
    };

    const [deletingId, setDeletingId] = useState(null);

    const deleteDomain = (id) => {
      setDeletingId(id);
      apiFetch({
        path: `vaptsecure/v1/domains/delete/${id}`,
        method: 'DELETE'
      }).then(() => {
        fetchData();
        setDeletingId(null);
      }).catch(() => {
        setDeletingId(null);
      });
    };

    const batchDeleteDomains = (ids) => {
      // Optimistic Delete
      setDomains(prev => prev.filter(d => !ids.includes(d.id)));

      return apiFetch({
        path: 'vaptsecure/v1/domains/batch-delete',
        method: 'POST',
        data: { ids }
      }).then(() => {
        setSaveStatus({ message: sprintf(__('%d domains deleted', 'vaptsecure'), ids.length), type: 'success' });
        setSelectedDomains([]);
        fetchData();
      }).catch(err => {
        setSaveStatus({ message: __('Batch delete failed', 'vaptsecure'), type: 'error' });
        fetchData(); // Rollback
      });
    };

    const updateDomainFeatures = (domainId, updatedFeatures) => {
      // Optimistic Update
      setDomains(prev => prev.map(d => d.id === domainId ? { ...d, features: updatedFeatures } : d));
      setSaveStatus({ message: __('Saving...', 'vaptsecure'), type: 'info' });

      apiFetch({
        path: 'vaptsecure/v1/domains/features',
        method: 'POST',
        data: { domain_id: domainId, features: updatedFeatures }
      }).then(() => {
        setSaveStatus({ message: __('Saved', 'vaptsecure'), type: 'success' });
      }).catch(err => {
        vaptLog.error('Domain features update failed:', err);
        setSaveStatus({ message: __('Error saving!', 'vaptsecure'), type: 'error' });
      });
    };

    const uploadJSON = (file) => {
      const formData = new FormData();
      formData.append('file', file);

      setLoading(true);
      apiFetch({
        path: 'vaptsecure/v1/upload-json',
        method: 'POST',
        body: formData,
      }).then((res) => {
        vaptLog.log('JSON uploaded', res);
        // Fetch fresh data (including file list) THEN update selection
        fetchData().then(() => { // Call fetchData without arguments to refresh all data, including dataFiles
          setSelectedFile(res.filename);
        });
      }).catch(err => {
        vaptLog.error('Upload error full object:', JSON.stringify(err));
        vaptLog.error('Upload error raw:', err);
        vaptLog.error('Upload error keys:', Object.keys(err));
        const errMsg = err.message || (err.data && err.data.message) || err.error || __('Error uploading JSON', 'vaptsecure');
        setAlertState({ message: errMsg });
        setLoading(false);
      });
    };

    const [allFiles, setAllFiles] = useState([]);
    const [hiddenFiles, setHiddenFiles] = useState([]);
    const [isManageModalOpen, setIsManageModalOpen] = useState(false);

    const fetchAllFiles = () => {
      apiFetch({ path: 'vaptsecure/v1/data-files/all' }).then(res => {
        // Clean display filenames (underscores to spaces)
        const cleaned = res.map(f => ({ ...f, display_name: f.filename.replace(/_/g, ' ') }));
        setAllFiles(cleaned);
        setHiddenFiles(res.filter(f => f.isHidden).map(f => f.filename));
      });
    };

    useEffect(() => {
      if (isManageModalOpen) {
        fetchAllFiles();
      }
    }, [isManageModalOpen]);

    const [manageSourcesStatus, setManageSourcesStatus] = useState(null);

    const updateHiddenFiles = (newHidden) => {
      setHiddenFiles(newHidden);
      setManageSourcesStatus('saving');
      apiFetch({
        path: 'vaptsecure/v1/update-hidden-files',
        method: 'POST',
        data: { hidden_files: newHidden }
      }).then(() => {
        // v3.12.1: Refresh both dropdown AND active selection
        Promise.all([
          apiFetch({ path: 'vaptsecure/v1/data-files' }),
          apiFetch({ path: 'vaptsecure/v1/active-file' })
        ]).then(([files, activeRes]) => {
          setDataFiles(files);
          if (activeRes.active_file) {
            setSelectedFile(activeRes.active_file);
            fetchData(activeRes.active_file);
          }
        });
        setManageSourcesStatus('saved');
        setTimeout(() => setManageSourcesStatus(null), 2000);
      }).catch(() => setManageSourcesStatus('error'));
    };

    const removeJSONFile = (filename) => {
      setManageSourcesStatus('saving');
      apiFetch({
        path: 'vaptsecure/v1/data-files/remove',
        method: 'POST',
        data: { filename }
      }).then(() => {
        fetchAllFiles(); // Refresh management list
        // v3.12.1: Refresh both dropdown AND active selection
        Promise.all([
          apiFetch({ path: 'vaptsecure/v1/data-files' }),
          apiFetch({ path: 'vaptsecure/v1/active-file' })
        ]).then(([files, activeRes]) => {
          setDataFiles(files);
          if (activeRes.active_file) {
            setSelectedFile(activeRes.active_file);
            fetchData(activeRes.active_file);
          }
        });
        setManageSourcesStatus('saved');
        setTimeout(() => setManageSourcesStatus(null), 2000);
      }).catch(() => {
        setManageSourcesStatus('error');
        setAlertState({ message: __('Failed to remove file from list', 'vaptsecure') });
      });
    };

    // v1.9.2 – Batch Revert: Preview affected features
    const previewBatchRevert = (overrides = {}) => {
      const incBroken = overrides.includeBroken !== undefined ? overrides.includeBroken : includeBroken;
      const incRelease = overrides.includeRelease !== undefined ? overrides.includeRelease : includeRelease;

      setBatchRevertModal(prev => ({ ...(prev || {}), previewData: (prev ? prev.previewData : null), isLoading: true, isExecuting: false }));

      apiFetch({
        path: 'vaptsecure/v1/features/preview-revert?include_broken=' + (incBroken ? '1' : '0') + '&include_release=' + (incRelease ? '1' : '0'),
        method: 'GET',
      }).then(res => {
        setBatchRevertModal({ previewData: res, isLoading: false, isExecuting: false });
      }).catch(err => {
        setSaveStatus({ message: err.message || __('Failed to preview revert', 'vaptsecure'), type: 'error' });
        setBatchRevertModal(null);
      });
    };

    // v1.9.2 – Batch Revert: Execute the revert
    const executeBatchRevert = () => {
      if (!batchRevertModal?.previewData) return;
      setBatchRevertModal(prev => ({ ...prev, isExecuting: true }));

      apiFetch({
        path: 'vaptsecure/v1/features/batch-revert',
        method: 'POST',
        data: { note: 'Batch revert to Draft via Workbench', include_broken: includeBroken, include_release: includeRelease }
      }).then(res => {
        setBatchRevertModal(null);
        setSaveStatus({
          message: sprintf(__('Successfully reverted %d features to Draft', 'vaptsecure'), res.reverted_count),
          type: 'success'
        });
        // Refresh data to show updated statuses
        fetchData(selectedFile);
        setTimeout(() => setSaveStatus(null), 5000);
      }).catch(err => {
        setSaveStatus({ message: err.message || __('Batch revert failed', 'vaptsecure'), type: 'error' });
        setBatchRevertModal(prev => ({ ...prev, isExecuting: false }));
      });
    };

    // v1.9.2 – Clear Enforcement Cache
    const clearEnforcementCache = () => {
      apiFetch({
        path: 'vaptsecure/v1/clear-cache',
        method: 'POST',
      }).then(res => {
        setSaveStatus({
          message: __('Enforcement cache cleared. Refresh the page to see updated features.', 'vaptsecure'),
          type: 'success'
        });
        setTimeout(() => setSaveStatus(null), 4000);
      }).catch(err => {
        setSaveStatus({ message: err.message || __('Failed to clear cache', 'vaptsecure'), type: 'error' });
      });
    };

    const tabs = [
      {
        name: 'features',
        title: __('Feature List', 'vaptsecure'),
        className: 'vapt-tab-features',
      },
      {
        name: 'license',
        title: __('License Management', 'vaptsecure'),
        className: 'vapt-tab-license',
      },
      {
        name: 'domains',
        title: __('Domain Features', 'vaptsecure'),
        className: 'vapt-tab-domains',
      },
      {
        name: 'build',
        title: __('Build Generator', 'vaptsecure'),
        className: 'vapt-tab-build',
      },
    ];

    if (error) {
      return el('div', { id: 'vapt-admin-dashboard--error', className: 'vapt-admin-wrap' }, [
        el('h1', null, __('VAPTSecure Clean Dashboard', 'vaptsecure')),
        el(Notice, { status: 'error', isDismissible: false }, error),
        el(Button, { isSecondary: true, onClick: () => fetchData() }, __('Retry', 'vaptsecure'))
      ]);
    }

    return el('div', { id: 'vapt-admin-dashboard--main', className: 'vapt-admin-wrap' }, [
      el('div', {
        className: 'vapt-dashboard-header-row',
        style: {
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          background: '#fff',
          padding: '0 20px',
          borderBottom: '1px solid #ccd0d4',
          marginBottom: '5px', // Reduced margin from 20px per request
          boxShadow: '0 1px 1px rgba(0,0,0,0.04)'
        }
      }, [
        // Left Column: Title
        el('div', { style: { display: 'flex', alignItems: 'baseline', gap: '10px' } }, [
          el('h1', { style: { margin: 0, fontSize: '20px', fontWeight: '600', color: '#1d2327', lineHeight: '1.2', padding: '15px 0' } }, __('VAPTSecure Clean Dashboard', 'vaptsecure')),
          el('span', { style: { fontSize: '11px', color: '#646970' } }, `v${settings.pluginVersion}`)
        ]),

        // Center Column: Custom Tabs
        el('div', { style: { display: 'flex', gap: '0' } }, tabs.map(tab =>
          el('button', {
            key: tab.name,
            className: `vapt-custom-tab ${activeTab === tab.name ? 'is-active' : ''}`,
            onClick: () => {
              setActiveTab(tab.name);
              localStorage.setItem('vaptsecure_admin_active_tab', tab.name);
            },
            style: {
              background: 'none',
              border: 'none',
              padding: '16px 15px',
              fontSize: '13px',
              fontWeight: activeTab === tab.name ? '600' : '400',
              color: activeTab === tab.name ? '#2271b1' : '#646970',
              borderBottom: activeTab === tab.name ? '3px solid #2271b1' : '3px solid transparent',
              cursor: 'pointer',
              transition: 'all 0.2s',
              margin: '0',
              boxShadow: 'none',
              outline: 'none'
            }
          }, tab.title)
        )),

        // Right Column: Badges + Batch Revert
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } }, [
          // v1.9.2 – Batch Revert to Draft button
          isSuper && el(Tooltip, { text: __('Revert all features in Develop status back to Draft. This will delete all history and implementation data.', 'vaptsecure') },
            el(Button, {
              id: 'vapt-batch-revert-btn',
              variant: 'secondary',
              isDestructive: true,
              onClick: () => previewBatchRevert(),
              style: { fontSize: '11px', height: '28px', padding: '0 10px', transition: 'all 0.2s', marginLeft: '5px' }
            }, __('↩️ Revert All to Draft', 'vaptsecure'))
          ),
          // v1.9.2 – Clear Enforcement Cache button
          isSuper && el(Tooltip, { text: __('Clear the enforcement cache to refresh the X-VAPT-Feature header list. Use this after implementing or reverting features.', 'vaptsecure') },
            el(Button, {
              id: 'vapt-clear-cache-btn',
              variant: 'secondary',
              onClick: () => clearEnforcementCache(),
              style: { fontSize: '11px', height: '28px', padding: '0 10px', transition: 'all 0.2s', marginLeft: '5px' }
            }, __('🔄 Clear Cache', 'vaptsecure'))
          )
        ])
      ]),
      saveStatus && el('div', {
        id: 'vapt-global-status-toast',
        className: `vapt-toast-notification is-${saveStatus.type === 'error' ? 'error' : 'success'}`
      }, saveStatus.message),

      (() => {
        let currentTabObj = tabs.find(t => t.name === activeTab) || tabs[0];
        switch (currentTabObj.name) {
          case 'features': return el(FeatureList, {
            key: selectedFile, // Force remount on file change to fix persistence
            features,
            schema,
            updateFeature,
            loading,
            dataFiles,
            selectedFile,
            allFiles,
            hiddenFiles,
            onUpdateHiddenFiles: updateHiddenFiles,
            manageSourcesStatus: manageSourcesStatus,
            onSelectFile: onSelectFile,
            onUpload: uploadJSON,
            isManageModalOpen,
            setIsManageModalOpen,
            onRemoveFile: removeJSONFile,
            designPromptConfig,
            setDesignPromptConfig,
            isPromptConfigModalOpen,
            setIsPromptConfigModalOpen,
            isMappingModalOpen,
            setIsMappingModalOpen,
            historyFeature,
            setHistoryFeature,
            designFeature,
            setDesignFeature,
            transitioning,
            setTransitioning,
            sortBySource,
            setSortBySource,
            sortSourceDirection,
            setSortSourceDirection,
            environmentProfile
          });
          case 'license': return el(LicenseManager, {
            domains,
            fetchData,
            isSuper,
            loading,
            addDomain,
            deleteDomain,
            globalSetConfirmState: setConfirmState,
            deletingId
          });
          case 'domains': return el(DomainFeatures, { domains, features, isDomainModalOpen, selectedDomain, setDomainModalOpen, setSelectedDomain, updateDomainFeatures, addDomain, deleteDomain, batchDeleteDomains, setConfirmState, selectedDomains, setSelectedDomains, dataFiles, selectedFile, onSelectFile });
          case 'build': return el(BuildGenerator, { domains, features, activeFile: selectedFile, setAlertState });
          default: return null;
        }
      })(),

      // Global Modals
      historyFeature && el(HistoryModal, {
        feature: historyFeature,
        updateFeature: updateFeature,
        onClose: () => setHistoryFeature(null)
      }),

      transitioning && el(TransitionNoteModal, {
        transitioning: transitioning,
        onConfirm: confirmTransition,
        onCancel: () => setTransitioning(null)
      }),

      // v1.9.2 – Batch Revert Modal
      batchRevertModal && el(BatchRevertModal, {
        isOpen: !!batchRevertModal,
        previewData: batchRevertModal.previewData,
        isLoading: batchRevertModal.isLoading,
        isExecuting: batchRevertModal.isExecuting,
        includeBroken: includeBroken,
        onToggleIncludeBroken: (val) => { setIncludeBroken(val); previewBatchRevert({ includeBroken: val }); },
        includeRelease: includeRelease,
        onToggleIncludeRelease: (val) => { setIncludeRelease(val); previewBatchRevert({ includeRelease: val }); },
        onRefresh: previewBatchRevert,
        onConfirm: executeBatchRevert,
        onCancel: () => setBatchRevertModal(null)
      }),

      designFeature && el(DesignModal, {
        feature: designFeature,
        updateFeature: updateFeature,
        designPromptConfig: designPromptConfig,
        setDesignPromptConfig: setDesignPromptConfig,
        setIsPromptConfigModalOpen: setIsPromptConfigModalOpen,
        selectedFile: selectedFile,
        fieldMapping: fieldMapping, // Pass mapping config to DesignModal
        rootAiInstructions: rootAiInstructions,
        rootGlobalSettings: rootGlobalSettings,
        onClose: () => !isPromptConfigModalOpen && setDesignFeature(null)
      }),

      isPromptConfigModalOpen && el(PromptConfigModal, {
        isOpen: isPromptConfigModalOpen,
        onClose: () => setIsPromptConfigModalOpen(false),
        feature: designFeature
      }),

      isMappingModalOpen && el(FieldMappingModal, {
        isOpen: isMappingModalOpen,
        onClose: () => setIsMappingModalOpen(false),
        fieldMapping: fieldMapping,
        setFieldMapping: setFieldMapping,
        allKeys: allKeys
      }),

      alertState && el(VAPTSECURE_AlertModal, {
        isOpen: true,
        message: alertState.message,
        type: alertState.type,
        onClose: () => setAlertState(null)
      }),
      confirmState && el(VAPTSECURE_ConfirmModal, {
        isOpen: !!confirmState,
        message: confirmState.message,
        isDestructive: confirmState.isDestructive,
        onConfirm: confirmState.onConfirm,
        onCancel: () => setConfirmState(null)
      })
    ]);
  };

  const init = () => {
    const container = document.getElementById('vapt-admin-root');
    if (!container) {
      vaptLog.debug('Root container #vapt-admin-root not found.');
      return;
    }

    vaptLog.log('Starting React mount...');

    if (typeof wp === 'undefined' || !wp.element) {
      vaptLog.error('WordPress React environment (wp.element) missing!');
      container.innerHTML = '<div class="notice notice-error"><p>Error: WordPress React components failed to load. Please check plugin dependencies.</p></div>';
      return;
    }

    try {
      const root = wp.element.createRoot ? wp.element.createRoot(container) : null;
      if (root) {
        root.render(el(ErrorBoundary, null, el(VAPTAdmin)));
      } else {
        wp.element.render(el(ErrorBoundary, null, el(VAPTAdmin)), container);
      }
      vaptLog.log('React app mounted successfully.');

      // Remove the loading notice if present
      const loadingNotice = container.querySelector('.notice-info');
      if (loadingNotice) loadingNotice.remove();

    } catch (err) {
      vaptLog.error('Mounting exception:', err);
      container.innerHTML = `<div class="notice notice-error"><p>Critical UI Mounting Error: ${err.message}</p></div>`;
    }
  };

  // Expose init globally for diagnostics
  window.vaptInit = init;

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    vaptLog.log('Document ready, running init');
    init();
  } else {
    vaptLog.log('Waiting for DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', init);
  }
})();
