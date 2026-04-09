(function () {
  if (typeof wp === 'undefined' || !wp.element || !wp.components || !wp.i18n) return;

  const vaptLog = window.vaptLog || {
    log: () => { },
    warn: () => { },
    error: (...args) => console.error('[VAPT]', ...args),
    debug: () => { },
    info: () => { }
  };

  const { createElement: el } = wp.element;
  const components = wp.components || {};
  const { __ } = wp.i18n || {};

  const { Modal, Button, Icon } = {
    Modal: components.Modal,
    Button: components.Button,
    Icon: components.Icon
  };

  if (!el || !Modal || !Button || !Icon || !__) return;

  class ErrorBoundary extends wp.element.Component {
    constructor(props) {
      super(props);
      this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError(error) {
      return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
      vaptLog.error('React Error:', error, errorInfo);
      this.setState({ errorInfo });
    }

    render() {
      if (this.state.hasError) {
        return el('div', { className: 'notice notice-error inline', style: { padding: '20px', margin: '20px' } }, [
          el('h3', null, 'Something went wrong rendering the VAPT Secure Dashboard.'),
          el('details', { style: { whiteSpace: 'pre-wrap', marginTop: '10px' } },
            this.state.error && this.state.error.toString(),
            el('br'),
            this.state.errorInfo && this.state.errorInfo.componentStack
          )
        ]);
      }
      return this.props.children;
    }
  }

  const VAPTSECURE_AlertModal = ({ isOpen, message, onClose, type = 'error' }) => {
    if (!isOpen) return null;
    return el(Modal, {
      title: type === 'error' ? __('Error', 'vaptsecure') : __('Notice', 'vaptsecure'),
      onRequestClose: onClose,
      style: { maxWidth: '400px' },
      className: 'vapt-alert-modal'
    }, [
      el('div', { style: { display: 'flex', gap: '15px', alignItems: 'flex-start', marginBottom: '20px' } }, [
        el(Icon, {
          icon: type === 'error' ? 'warning' : 'info',
          size: 32,
          style: {
            color: type === 'error' ? '#dc2626' : '#2563eb',
            background: type === 'error' ? '#fef2f2' : '#eff6ff',
            padding: '8px',
            borderRadius: '50%',
            flexShrink: 0
          }
        }),
        el('div', { style: { paddingTop: '4px' } }, [
          el('h3', { style: { margin: '0 0 8px 0', fontSize: '16px', fontWeight: 600 } }, type === 'error' ? 'Action Failed' : 'Notice'),
          el('p', { style: { margin: 0, fontSize: '14px', color: '#4b5563', lineHeight: '1.5' } }, message)
        ])
      ]),
      el('div', { style: { textAlign: 'right', borderTop: '1px solid #e5e7eb', paddingTop: '15px', marginTop: '10px' } },
        el(Button, { isPrimary: true, onClick: onClose }, __('OK', 'vaptsecure'))
      )
    ]);
  };

  const VAPTSECURE_ConfirmModal = ({ isOpen, message, onConfirm, onCancel, confirmLabel = __('Yes', 'vaptsecure'), isDestructive = false }) => {
    if (!isOpen) return null;
    return el(Modal, {
      title: __('Confirmation', 'vaptsecure'),
      onRequestClose: onCancel,
      className: 'vapt-confirm-modal-react'
    }, [
      el('div', { className: 'vapt-modal-body' }, [
        el('div', { style: { display: 'flex', gap: '15px', alignItems: 'flex-start', marginBottom: '20px' } }, [
          el(Icon, {
            icon: 'warning',
            size: 32,
            style: {
              color: '#d97706',
              background: '#fffbeb',
              padding: '8px',
              borderRadius: '50%',
              flexShrink: 0
            }
          }),
          el('div', { style: { paddingTop: '4px' } }, [
            el('h3', { style: { margin: '0 0 8px 0', fontSize: '16px', fontWeight: 600 } }, __('Are you sure?', 'vaptsecure')),
            el('p', { style: { margin: 0, fontSize: '14px', color: '#4b5563', lineHeight: '1.5', whiteSpace: 'pre-line' } }, message)
          ])
        ])
      ]),
      el('div', { style: { display: 'flex', justifyContent: 'flex-end', gap: '10px', borderTop: '1px solid #e5e7eb', paddingTop: '15px', marginTop: '10px' } }, [
        el(Button, { isSecondary: true, onClick: onCancel }, __('Cancel', 'vaptsecure')),
        el(Button, { isDestructive: isDestructive, isPrimary: !isDestructive, onClick: onConfirm }, confirmLabel)
      ])
    ]);
  };

  window.VAPTSECURE_ErrorBoundary = window.VAPTSECURE_ErrorBoundary || ErrorBoundary;
  window.VAPTSECURE_AlertModal = window.VAPTSECURE_AlertModal || VAPTSECURE_AlertModal;
  window.VAPTSECURE_ConfirmModal = window.VAPTSECURE_ConfirmModal || VAPTSECURE_ConfirmModal;
})();
