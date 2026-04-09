// AlertModal component for displaying alerts/notifications

(function () {
  const { createElement: el } = wp.element || {};
  const components = wp.components || {};
  const { Modal, Button, Dashicon } = components;
  const { __ } = wp.i18n || {};

  const AlertModal = ({ 
    isOpen, 
    message, 
    onClose, 
    type = 'error',
    title = null 
  }) => {
    if (!isOpen) return null;

    const typeConfig = {
      error: {
        icon: 'warning',
        color: '#d63638',
        defaultTitle: __('Error', 'vaptsecure')
      },
      success: {
        icon: 'yes-alt',
        color: '#46b450',
        defaultTitle: __('Success', 'vaptsecure')
      },
      warning: {
        icon: 'flag',
        color: '#f0b849',
        defaultTitle: __('Warning', 'vaptsecure')
      },
      info: {
        icon: 'info',
        color: '#2271b1',
        defaultTitle: __('Information', 'vaptsecure')
      }
    };

    const config = typeConfig[type] || typeConfig.error;
    const modalTitle = title || config.defaultTitle;

    return el(Modal, {
      title: el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
        el(Dashicon, { icon: config.icon, style: { color: config.color } }),
        modalTitle
      ),
      onRequestClose: onClose,
      className: `vaptsecure-alert-modal vaptsecure-alert-${type}`
    },
      el('div', { style: { padding: '20px 0' } },
        el('p', { style: { marginBottom: '20px', fontSize: '14px', lineHeight: '1.5', color: '#1e1e1e' } }, message)
      ),
      el('div', { style: { display: 'flex', justifyContent: 'flex-end', paddingTop: '20px', borderTop: '1px solid #ddd' } },
        el(Button, {
          isPrimary: true,
          onClick: onClose
        }, __('OK', 'vaptsecure'))
      )
    );
  };

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  if (!window.vaptAdmin.shared) window.vaptAdmin.shared = {};
  window.vaptAdmin.shared.AlertModal = AlertModal;
})();