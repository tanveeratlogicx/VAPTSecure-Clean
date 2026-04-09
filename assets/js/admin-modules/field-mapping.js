(function () {
  if (typeof wp === 'undefined' || !wp.element || !wp.components || !wp.i18n) return;

  const { useState, createElement: el } = wp.element;
  const { __, sprintf } = wp.i18n || {};
  const components = wp.components || {};

  const { Modal, Button, SelectControl } = {
    Modal: components.Modal,
    Button: components.Button,
    SelectControl: components.SelectControl
  };

  if (!useState || !el || !__ || !sprintf || !Modal || !Button || !SelectControl) return;

  const resolvePath = (obj, path) => {
    if (!path) return undefined;
    return path.split('.').reduce((prev, curr) => (prev && prev[curr] !== undefined) ? prev[curr] : undefined, obj);
  };

  const getMappedContent = (obj, mappingKey, fallbackKey, fieldMapping) => {
    if (fieldMapping && fieldMapping[mappingKey]) {
      const mapped = resolvePath(obj, fieldMapping[mappingKey]);
      if (mapped) return mapped;
    }
    return obj ? obj[fallbackKey] : undefined;
  };

  const FieldMappingModal = ({ isOpen, onClose, fieldMapping, setFieldMapping, allKeys }) => {
    const renderMappingSelect = (label, key) => {
      return el(SelectControl, {
        id: `vapt-mapping-select-${key}`,
        label: label,
        value: fieldMapping[key] || '',
        options: [{ label: __('--- Select Source Field ---', 'vaptsecure'), value: '' }, ...allKeys.map(k => ({ label: k, value: k }))],
        onChange: (val) => setFieldMapping({ ...fieldMapping, [key]: val }),
        style: { marginBottom: '15px' }
      });
    };

    const handleAutoMap = () => {
      const newMapping = { ...fieldMapping };
      let mappedCount = 0;
      const mappingDetails = [];

      const findBestMatch = (keywords, targetFieldName = '') => {
        const matches = [];

        allKeys.forEach(field => {
          const fieldLower = field.toLowerCase();
          let bestScore = 0;
          let bestKeyword = '';

          keywords.forEach(keyword => {
            const keywordLower = keyword.toLowerCase();
            let score = 0;

            const targetInKeyword = targetFieldName && keywordLower.includes(targetFieldName.toLowerCase());

            if (fieldLower === keywordLower) {
              score = 100;
              if (targetInKeyword) score += 20;
            } else if (fieldLower.endsWith('.' + keywordLower)) {
              score = 95;
              if (targetInKeyword) score += 15;
            } else if (fieldLower.startsWith(keywordLower + '.')) {
              score = 90;
              if (targetInKeyword) score += 15;
            } else if (fieldLower.includes('.' + keywordLower + '.')) {
              score = 85;
              if (targetInKeyword) score += 10;
            } else if (fieldLower.includes('_' + keywordLower + '_')) {
              score = 80;
              if (targetInKeyword) score += 10;
            } else if (fieldLower.includes(keywordLower)) {
              const lengthPenalty = Math.min(20, (fieldLower.length - keywordLower.length) * 2);
              score = 70 - lengthPenalty;
              if (targetInKeyword) score += 10;
            } else {
              const similarity = keywordLower.split('').filter(c => fieldLower.includes(c)).length / keywordLower.length;
              if (similarity > 0.7) {
                score = Math.floor(similarity * 60);
                if (targetInKeyword) score += 5;
              }
            }

            if (targetFieldName && fieldLower === targetFieldName.toLowerCase()) {
              score += 25;
            }

            if (targetFieldName) {
              if (fieldLower.includes('.' + targetFieldName.toLowerCase() + '.')) score += 15;
              if (fieldLower.endsWith('.' + targetFieldName.toLowerCase())) score += 20;
              if (fieldLower.startsWith(targetFieldName.toLowerCase() + '.')) score += 20;

              if (targetFieldName === 'operational_notes' && fieldLower.endsWith('.context')) score += 30;
              if (targetFieldName === 'verification_steps' && fieldLower.includes('verification_steps')) score += 25;
              if (targetFieldName === 'verification_steps' && fieldLower.endsWith('.steps')) score += 20;
            }

            if (score > bestScore) {
              bestScore = score;
              bestKeyword = keyword;
            }
          });

          if (bestScore > 40) {
            matches.push({
              field,
              score: bestScore,
              keyword: bestKeyword
            });
          }
        });

        matches.sort((a, b) => {
          if (b.score !== a.score) return b.score - a.score;
          return a.field.length - b.field.length;
        });

        return matches.length > 0 ? matches[0].field : '';
      };

      const autoMapField = (key, keywords) => {
        if (!newMapping[key]) {
          const match = findBestMatch(keywords, key);
          if (match) {
            newMapping[key] = match;
            mappedCount++;
            mappingDetails.push({
              target: key,
              source: match,
              keywords: keywords.slice(0, 3)
            });
          }
        }
      };

      autoMapField('description', ['summary', 'description', 'desc', 'overview', 'details', 'info', 'text', 'content', 'explanation', 'definition']);
      autoMapField('severity', ['severity', 'level', 'risk_level', 'risk.level', 'priority', 'criticality', 'impact', 'risk', 'severity.level', 'severity_level']);

      autoMapField('ui_layout', ['ui_layout', 'ui-layout', 'uiLayout', 'layout', 'ui', 'interface', 'design', 'ui.layout', 'ui_layout_schema', 'interface_layout', 'ui_design', 'structure', 'arrangement', 'layout.ui', 'ui_schema', 'interface_schema', 'design_layout', 'visual_layout', 'page_layout', 'template_layout']);
      autoMapField('components', ['components', 'ui_components', 'ui-components', 'uiComponents', 'ui.components', 'fields', 'elements', 'controls', 'widgets', 'parts', 'ui_elements', 'component_list', 'ui_elements_list', 'fields_list', 'controls_list', 'widgets_list', 'ui_parts', 'interface_components', 'design_components', 'visual_components', 'ui_controls']);
      autoMapField('actions', ['actions', 'ui_actions', 'ui-actions', 'uiActions', 'ui.actions', 'buttons', 'action', 'operations', 'functions', 'interactions', 'handlers', 'action_list', 'buttons_list', 'operations_list', 'functions_list', 'ui_buttons', 'interface_actions', 'design_actions', 'visual_actions', 'user_actions', 'click_actions', 'event_handlers']);

      autoMapField('available_platforms', ['available_platforms', 'platforms', 'platform_list', 'supported_platforms', 'platform', 'platforms.available', 'platforms_list', 'compatible_platforms']);
      autoMapField('platform_implementations', ['platform_implementations', 'implementations', 'enforcer_map', 'implementation', 'enforcer', 'platform.implementations', 'enforcement', 'rules', 'configurations']);

      autoMapField('operational_notes', [
        'operational_notes.context',
        'context.operational_notes',
        'operational_notes',
        'operational_context',
        'operation_context',
        'context',
        'operation_notes',
        'operation_details',
        'notes',
        'summary',
        'remarks',
        'comments',
        'guidance',
        'instructions',
        'documentation',
        'background',
        'environment',
        'contextual',
        'operationalContext',
        'operational-context',
        'op_context',
        'op_notes',
        'opnotes',
        'opcontext',
        'notes.operational',
        'description.context',
        'context.description',
        'info',
        'additional_info',
        'additional_info.context',
        'context.additional',
        'notes.context',
        'context.notes',
        'operational.context'
      ]);

      autoMapField('verification_steps', [
        'testing.verification_steps',
        'verification.steps',
        'verification_steps',
        'manual_verification',
        'steps',
        'test_steps',
        'testing_steps',
        'verification',
        'test',
        'protocol',
        'procedure',
        'instructions',
        'checklist',
        'guide',
        'verify',
        'validation'
      ]);

      autoMapField('verification_command', ['verification.command', 'verification_command', 'verification command', 'test command', 'verification', 'command', 'test.command', 'test_command', 'cli', 'terminal', 'shell']);
      autoMapField('verification_expected', ['verification.expected', 'verification_expected', 'expected output', 'test result', 'expected', 'verification', 'output', 'result', 'expected_result', 'expected.output', 'test.expected']);

      autoMapField('risk_id', ['risk_id', 'id', 'risk.id', 'riskId', 'risk-id', 'riskId', 'risk']);
      autoMapField('title', ['title', 'name', 'label', 'risk.title', 'risk_title', 'riskTitle', 'risk-name', 'risk_label']);
      autoMapField('category', ['category', 'risk.category', 'risk_category', 'group', 'type', 'classification']);
      autoMapField('owasp_cwe', ['cwe', 'owasp_cwe', 'owasp.cwe', 'owasp.cwe_id', 'cwe_id', 'cwe-id']);
      autoMapField('owasp_top_10_2025', ['owasp.owasp_top_10_2025', 'owasp top 10', 'owasp 2025', 'owasp_top_10_2025', 'owasp', 'owasp_top10', 'owasp.top10', 'owasp_top_10', 'top10', 'top_10']);

      setFieldMapping(newMapping);

      if (mappedCount === 0) {
        alert(__('No new matching fields found.', 'vaptsecure'));
      } else {
        let message = sprintf(__('Auto-mapped %d new fields:\n\n', 'vaptsecure'), mappedCount);
        mappingDetails.forEach((detail, index) => {
          message += sprintf(__('%d. %s → %s\n', 'vaptsecure'),
            index + 1,
            detail.target,
            detail.source
          );
        });
        message += '\n' + __('Review the mappings in the modal.', 'vaptsecure');
        alert(message);

        if (window.VAPT_DEBUG) {
          console.log('[VAPT] Auto-map details:', mappingDetails);
        }
      }
    };

    const handleReset = () => {
      if (confirm(__('Are you sure you want to clear all field mappings?', 'vaptsecure'))) {
        setFieldMapping({});
      }
    };

    if (!isOpen) return null;

    return el(Modal, {
      title: null,
      onRequestClose: onClose,
      className: 'vapt-mapping-modal no-header-modal',
      style: {
        width: '600px',
        height: '80vh',
        maxWidth: '90vw',
        maxHeight: '900px',
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        padding: 0
      }
    }, [
      el('style', null, `
        .no-header-modal .components-modal__header {
          display: none !important;
        }
        .no-header-modal .components-modal__content {
          padding: 0 !important;
          margin: 0 !important;
          overflow: hidden !important;
          display: flex !important;
          flex-direction: column !important;
          height: 100% !important;
        }
        .vapt-mapping-scroll-body {
          scrollbar-width: thin;
          scrollbar-color: #949494 #f1f1f1;
        }
        .vapt-mapping-scroll-body::-webkit-scrollbar {
          width: 10px;
        }
        .vapt-mapping-scroll-body::-webkit-scrollbar-track {
          background: #f1f1f1;
        }
        .vapt-mapping-scroll-body::-webkit-scrollbar-thumb {
          background: #949494;
          border-radius: 5px;
          border: 2px solid #f1f1f1;
        }
        .vapt-mapping-scroll-body::-webkit-scrollbar-thumb:hover {
          background: #787878;
        }
      `),
      el('div', {
        id: 'vapt-mapping-modal-container',
        style: {
          display: 'flex',
          flexDirection: 'column',
          height: '100%',
          width: '100%',
          overflow: 'hidden',
          position: 'relative',
          background: '#fff'
        }
      }, [
        el('div', {
          id: 'vapt-mapping-modal-header',
          style: {
            padding: '18px 25px',
            borderBottom: '1px solid #dcdcde',
            background: '#fff',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            flex: '0 0 auto',
            zIndex: 100
          }
        }, [
          el('div', { style: { flex: '1 1 auto', display: 'flex', flexDirection: 'column', gap: '4px', paddingRight: '20px' } }, [
            el('h2', { style: { margin: '0', fontSize: '18px', fontWeight: '600', color: '#1d2327', whiteSpace: 'nowrap' } }, __('Mapping Configuration', 'vaptsecure')),
            el('p', { style: { margin: '0', fontSize: '12px', color: '#646970', lineHeight: '1.4', whiteSpace: 'nowrap' } },
              __('Map JSON fields for context-aware prompts.', 'vaptsecure')
            ),
            el('div', {
              style: {
                display: 'flex',
                alignItems: 'center',
                gap: '4px',
                padding: '2px 6px',
                fontSize: '11px',
                fontWeight: '600',
                color: '#1e3a8a',
                background: '#f0f6fb',
                border: '1px solid #c8d7e1',
                borderRadius: '4px',
                height: '22px',
                whiteSpace: 'nowrap',
                marginTop: '4px',
                width: 'fit-content'
              },
              title: __('Total number of mapping fields in the modal', 'vaptsecure')
            }, sprintf(__('Total Fields: %d', 'vaptsecure'), 16))
          ]),
          el('div', { id: 'vapt-mapping-modal-actions', style: { display: 'flex', gap: '8px', alignItems: 'center', flexShrink: 0 } }, [
            el(Button, { id: 'vapt-button-automap', isSecondary: true, onClick: handleAutoMap, style: { height: '32px' } }, __('Auto Map', 'vaptsecure')),
            el(Button, { id: 'vapt-button-reset', isDestructive: true, isSecondary: true, onClick: handleReset, style: { height: '32px' } }, __('Reset', 'vaptsecure')),
            el(Button, { id: 'vapt-button-cancel', isTertiary: true, onClick: onClose, style: { height: '32px' } }, __('Cancel', 'vaptsecure')),
            el(Button, { id: 'vapt-button-done', isPrimary: true, onClick: onClose, style: { height: '32px' } }, __('Done', 'vaptsecure'))
          ])
        ]),
        el('div', {
          id: 'vapt-mapping-modal-body',
          className: 'vapt-mapping-scroll-body',
          style: {
            flex: '1 1 auto',
            overflowY: 'auto',
            padding: '25px',
            background: '#fcfcfc',
            position: 'relative',
            maxHeight: '590px',
          }
        }, [
          el('div', { style: { display: 'flex', flexDirection: 'column', gap: '0' } }, [
            el('h3', { id: 'vapt-mapping-section-core', style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#8c8f94', borderBottom: '1px solid #dcdcde', paddingBottom: '8px', marginBottom: '15px', marginTop: '0', letterSpacing: '0.5px' } }, __('Core Context Fields')),
            renderMappingSelect(__('Description / Summary', 'vaptsecure'), 'description'),
            renderMappingSelect(__('Severity Level', 'vaptsecure'), 'severity'),

            el('h3', { id: 'vapt-mapping-section-ui', style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#8c8f94', borderBottom: '1px solid #dcdcde', paddingBottom: '8px', marginBottom: '15px', marginTop: '20px', letterSpacing: '0.5px' } }, __('UI Schema Parameters')),
            el('p', { style: { margin: '0 0 12px 0', fontSize: '12px', color: '#646970', lineHeight: '1.5' } },
              __('Fields required for generating >95% accurate interactive UI schema.', 'vaptsecure')
            ),
            renderMappingSelect(__('UI Layout Object', 'vaptsecure'), 'ui_layout'),
            renderMappingSelect(__('Components Array', 'vaptsecure'), 'components'),
            renderMappingSelect(__('Actions Array', 'vaptsecure'), 'actions'),

            el('h3', { id: 'vapt-mapping-section-platform', style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#8c8f94', borderBottom: '1px solid #dcdcde', paddingBottom: '8px', marginBottom: '15px', marginTop: '20px', letterSpacing: '0.5px' } }, __('Platform & Enforcement')),
            el('p', { style: { margin: '0 0 12px 0', fontSize: '12px', color: '#646970', lineHeight: '1.5' } },
              __('Controls which platform list and implementations are injected into the AI prompt.', 'vaptsecure')
            ),
            renderMappingSelect(__('Available Platforms (array)', 'vaptsecure'), 'available_platforms'),
            renderMappingSelect(__('Platform Implementations (object)', 'vaptsecure'), 'platform_implementations'),

            el('h3', { id: 'vapt-mapping-section-additional', style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#8c8f94', borderBottom: '1px solid #dcdcde', paddingBottom: '8px', marginBottom: '15px', marginTop: '20px', letterSpacing: '0.5px' } }, __('Additional Context')),
            el('p', { style: { margin: '0 0 12px 0', fontSize: '12px', color: '#646970', lineHeight: '1.5' } },
              __('Map specific fields for operational notes and manual verification steps.', 'vaptsecure')
            ),
            renderMappingSelect(__('Operational Context', 'vaptsecure'), 'operational_notes'),
            renderMappingSelect(__('Verification Steps', 'vaptsecure'), 'verification_steps'),
            renderMappingSelect(__('Verification Command', 'vaptsecure'), 'verification_command'),
            renderMappingSelect(__('Verification Expected', 'vaptsecure'), 'verification_expected'),

            el('h3', { id: 'vapt-mapping-section-identification', style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#8c8f94', borderBottom: '1px solid #dcdcde', paddingBottom: '8px', marginBottom: '15px', marginTop: '20px', letterSpacing: '0.5px' } }, __('Risk Identification & Compliance')),
            el('p', { style: { margin: '0 0 12px 0', fontSize: '12px', color: '#646970', lineHeight: '1.5' } },
              __('Map risk identification and OWASP compliance fields for enhanced Design Implementation modal.', 'vaptsecure')
            ),
            renderMappingSelect(__('Risk ID', 'vaptsecure'), 'risk_id'),
            renderMappingSelect(__('Title', 'vaptsecure'), 'title'),
            renderMappingSelect(__('Category', 'vaptsecure'), 'category'),
            renderMappingSelect(__('OWASP CWE', 'vaptsecure'), 'owasp_cwe'),
            renderMappingSelect(__('OWASP Top 10 2025', 'vaptsecure'), 'owasp_top_10_2025'),
          ])
        ])
      ])
    ]);
  };

  window.VAPTSECURE_resolvePath = window.VAPTSECURE_resolvePath || resolvePath;
  window.VAPTSECURE_getMappedContent = window.VAPTSECURE_getMappedContent || getMappedContent;
  window.VAPTSECURE_FieldMappingModal = window.VAPTSECURE_FieldMappingModal || FieldMappingModal;
})();
