// ConfirmDialog component for confirmation modals

(function () {
  const { createElement: el } = wp.element || {};
  const components = wp.components || {};
  const { Modal, Button, Dashicon } = components;
  const { __ } = wp.i18n || {};

  const ConfirmDialog = ({ 
    isOpen, 
    message, 
    onConfirm, 
    onCancel, 
    confirmLabel = __('Yes', 'vaptsecure'), 
    cancelLabel = __('Cancel', 'vaptsecure'),
    isDestructive = false 
  }) => {
    if (!isOpen) return null;

    return el(Modal, {
      title: __('Confirm Action', 'vaptsecure'),
      onRequestClose: onCancel,
      className: 'vaptsecure-confirm-modal'
    },
      el('div', { style: { padding: '20px 0' } },
        el('p', { style: { marginBottom: '20px', fontSize: '14px', lineHeight: '1.5' } }, message)
      ),
      el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', paddingTop: '20px', borderTop: '1px solid #ddd' } },
        el(Button, {
          isSecondary: true,
          onClick: onCancel
        }, cancelLabel),
        el(Button, {
          isPrimary: !isDestructive,
          isDestructive: isDestructive,
          onClick: onConfirm
        },
          isDestructive && el(Dashicon, { icon: 'warning', style: { marginRight: '5px' } }),
          confirmLabel
        )
      )
    );
  };

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  if (!window.vaptAdmin.shared) window.vaptAdmin.shared = {};
  window.vaptAdmin.shared.ConfirmDialog = ConfirmDialog;
})();