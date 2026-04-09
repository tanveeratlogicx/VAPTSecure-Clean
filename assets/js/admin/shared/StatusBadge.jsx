// StatusBadge component for displaying feature status with colors

(function () {
  const { createElement: el } = wp.element || {};
  const components = wp.components || {};
  const { Tooltip } = components;
  const { __ } = wp.i18n || {};

  const StatusBadge = ({ status, size = 'medium', showTooltip = true }) => {
    const statusColors = {
      'draft': { bg: '#f0f0f0', text: '#757575', icon: 'edit' },
      'develop': { bg: '#e1f5fe', text: '#0277bd', icon: 'hammer' },
      'test': { bg: '#fff3e0', text: '#ef6c00', icon: 'testimonial' },
      'release': { bg: '#e8f5e9', text: '#2e7d32', icon: 'yes-alt' },
      'deprecated': { bg: '#fce4ec', text: '#ad1457', icon: 'dismiss' },
      'broken': { bg: '#ffebee', text: '#c62828', icon: 'warning' }
    };

    const statusLabels = {
      'draft': __('Draft', 'vaptsecure'),
      'develop': __('Develop', 'vaptsecure'),
      'test': __('Test', 'vaptsecure'),
      'release': __('Release', 'vaptsecure'),
      'deprecated': __('Deprecated', 'vaptsecure'),
      'broken': __('Broken', 'vaptsecure')
    };

    const sizes = {
      small: { fontSize: '11px', padding: '2px 6px', borderRadius: '2px' },
      medium: { fontSize: '12px', padding: '3px 8px', borderRadius: '3px' },
      large: { fontSize: '13px', padding: '4px 10px', borderRadius: '4px' }
    };

    const config = statusColors[status] || statusColors.draft;
    const label = statusLabels[status] || status;
    const sizeStyle = sizes[size] || sizes.medium;

    const badgeStyle = {
      display: 'inline-block',
      backgroundColor: config.bg,
      color: config.text,
      fontSize: sizeStyle.fontSize,
      fontWeight: '500',
      padding: sizeStyle.padding,
      borderRadius: sizeStyle.borderRadius,
      lineHeight: 1,
      textTransform: 'uppercase',
      letterSpacing: '0.5px',
      border: `1px solid ${config.text}20`
    };

    const badge = el('span', { style: badgeStyle }, label);

    if (showTooltip) {
      return el(Tooltip, { text: `${__('Status:', 'vaptsecure')} ${label}` }, badge);
    }

    return badge;
  };

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  if (!window.vaptAdmin.shared) window.vaptAdmin.shared = {};
  window.vaptAdmin.shared.StatusBadge = StatusBadge;
})();