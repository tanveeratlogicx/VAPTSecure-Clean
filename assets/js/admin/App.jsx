// Main App component for VAPT Secure Admin
// Handles layout, tab routing, and state management

(function () {
  const { useState, useEffect, useMemo, Fragment, createElement: el } = wp.element || {};
  const components = wp.components || {};
  const { TabPanel, Panel, PanelBody, PanelRow, Button, Dashicon, ToggleControl, SelectControl, Modal, TextControl, Spinner, Notice, Placeholder, Dropdown, CheckboxControl, BaseControl, Icon, TextareaControl, Card, CardHeader, CardBody, Tooltip } = components;
  const { __, sprintf } = wp.i18n || {};
  const apiFetch = wp.apiFetch;

  // Import modular components
  const FeatureList = window.vaptAdmin.FeatureList;
  const DomainManager = window.vaptAdmin.DomainManager;
  const BuildGenerator = window.vaptAdmin.BuildGenerator;
  const SecurityDashboard = window.vaptAdmin.SecurityDashboard;
  const LicenseManager = window.vaptAdmin.LicenseManager;
  const EnforcementToggles = window.vaptAdmin.EnforcementToggles;
  const StatusBadge = window.vaptAdmin.shared.StatusBadge;
  const ConfirmDialog = window.vaptAdmin.shared.ConfirmDialog;
  const DataTable = window.vaptAdmin.shared.DataTable;
  const ApiHelper = window.vaptAdmin.ApiHelper;

  const App = ({ settings, isSuper, apiFetch, __, sprintf }) => {
    const [activeTab, setActiveTab] = useState('features');
    const [features, setFeatures] = useState([]);
    const [domains, setDomains] = useState([]);
    const [dataFiles, setDataFiles] = useState([]);
    const [selectedFile, setSelectedFile] = useState(settings.activeFile || 'interface_schema_v2.0.json');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [alertState, setAlertState] = useState({ isOpen: false, message: '', type: 'error' });
    const [confirmState, setConfirmState] = useState({ isOpen: false, message: '', onConfirm: null, onCancel: null, confirmLabel: __('Yes', 'vaptsecure'), isDestructive: false });
    const [globalEnforcement, setGlobalEnforcement] = useState({ enabled: true, strict_mode: false });
    const [securityStats, setSecurityStats] = useState(null);
    const [licenseStatus, setLicenseStatus] = useState(null);

    // Fetch initial data
    useEffect(() => {
      fetchData();
    }, [selectedFile]);

    const fetchData = async () => {
      setLoading(true);
      setError(null);
      
      try {
        const [featuresData, domainsData, filesData, enforcementData, securityData, licenseData] = await Promise.allSettled([
          ApiHelper.getFeatures(selectedFile),
          ApiHelper.getDomains(),
          ApiHelper.getDataFiles(),
          ApiHelper.getGlobalEnforcement(),
          ApiHelper.getSecurityStats(),
          ApiHelper.getLicenseStatus()
        ]);

        if (featuresData.status === 'fulfilled') setFeatures(featuresData.value || []);
        if (domainsData.status === 'fulfilled') setDomains(domainsData.value || []);
        if (filesData.status === 'fulfilled') setDataFiles(filesData.value || []);
        if (enforcementData.status === 'fulfilled') setGlobalEnforcement(enforcementData.value || { enabled: true });
        if (securityData.status === 'fulfilled') setSecurityStats(securityData.value);
        if (licenseData.status === 'fulfilled') setLicenseStatus(licenseData.value);

        // Check for errors
        const errors = [featuresData, domainsData].filter(r => r.status === 'rejected');
        if (errors.length > 0) {
          setError(__('Failed to load some data. Check console for details.', 'vaptsecure'));
          console.error('Data loading errors:', errors.map(e => e.reason));
        }
      } catch (err) {
        setError(sprintf(__('Error loading data: %s', 'vaptsecure'), err.message));
        console.error('Fetch data error:', err);
      } finally {
        setLoading(false);
      }
    };

    const handleFileChange = (file) => {
      setSelectedFile(file);
    };

    const handleFeatureUpdate = (updatedFeature) => {
      setFeatures(prev => prev.map(f => 
        f.key === updatedFeature.key ? { ...f, ...updatedFeature } : f
      ));
    };

    const handleDomainUpdate = (updatedDomains) => {
      setDomains(updatedDomains);
    };

    const handleGlobalEnforcementUpdate = (updatedEnforcement) => {
      setGlobalEnforcement(updatedEnforcement);
    };

    const handleRefresh = () => {
      fetchData();
    };

    if (loading && features.length === 0) {
      return el('div', { style: { padding: '40px', textAlign: 'center' } },
        el(Spinner, {}),
        el('p', {}, __('Loading VAPT Secure Admin...', 'vaptsecure'))
      );
    }

    if (error && features.length === 0) {
      return el('div', { style: { padding: '40px' } },
        el(Notice, { status: 'error', isDismissible: false },
          el('p', {}, error),
          el(Button, { isSecondary: true, onClick: fetchData }, __('Retry', 'vaptsecure'))
        )
      );
    }

    const tabs = [
      {
        name: 'features',
        title: __('Features', 'vaptsecure'),
        icon: 'admin-generic',
        className: 'features-tab'
      },
      {
        name: 'domains',
        title: __('Domains', 'vaptsecure'),
        icon: 'admin-site',
        className: 'domains-tab'
      },
      {
        name: 'build',
        title: __('Build', 'vaptsecure'),
        icon: 'download',
        className: 'build-tab'
      },
      {
        name: 'security',
        title: __('Security', 'vaptsecure'),
        icon: 'shield',
        className: 'security-tab'
      }
    ];

    // Add license tab for super admins
    if (isSuper) {
      tabs.push({
        name: 'license',
        title: __('License', 'vaptsecure'),
        icon: 'admin-network',
        className: 'license-tab'
      });
    }

    return el(Fragment, {},
      // Header
      el('div', { style: { marginBottom: '20px' } },
        el('h1', { style: { marginBottom: '10px' } }, __('VAPT Secure Admin', 'vaptsecure')),
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '20px', flexWrap: 'wrap' } },
          el(SelectControl, {
            label: __('Active Data File', 'vaptsecure'),
            value: selectedFile,
            options: dataFiles.map(file => ({ label: file.name, value: file.name })),
            onChange: handleFileChange,
            style: { maxWidth: '300px' }
          }),
          el(Button, { 
            isSecondary: true, 
            onClick: handleRefresh,
            style: { alignSelf: 'flex-end' }
          },
            el(Dashicon, { icon: 'update' }),
            __('Refresh', 'vaptsecure')
          )
        )
      ),

      // Enforcement toggles
      el(EnforcementToggles, {
        globalEnforcement,
        onUpdate: handleGlobalEnforcementUpdate,
        setAlertState
      }),

      // Main tabs
      el(TabPanel, {
        className: 'vaptsecure-tab-panel',
        activeClass: 'is-active',
        onSelect: setActiveTab,
        tabs: tabs,
        initialTabName: activeTab
      }, (tab) => {
        switch (tab.name) {
          case 'features':
            return el(FeatureList, {
              features,
              domains,
              dataFiles,
              selectedFile,
              onSelectFile: handleFileChange,
              onFeatureUpdate: handleFeatureUpdate,
              onRefresh: handleRefresh,
              setAlertState,
              setConfirmState,
              isSuper
            });
          
          case 'domains':
            return el(DomainManager, {
              domains,
              features,
              dataFiles,
              selectedFile,
              onDomainUpdate: handleDomainUpdate,
              setAlertState,
              setConfirmState,
              isSuper
            });
          
          case 'build':
            return el(BuildGenerator, {
              domains,
              features,
              activeFile: selectedFile,
              setAlertState
            });
          
          case 'security':
            return el(SecurityDashboard, {
              securityStats,
              setAlertState,
              isSuper
            });
          
          case 'license':
            return el(LicenseManager, {
              licenseStatus,
              setAlertState,
              isSuper,
              onRefresh: handleRefresh
            });
          
          default:
            return el(Placeholder, {},
              el('p', {}, __('Select a tab to continue', 'vaptsecure'))
            );
        }
      }),

      // Alert modal
      alertState.isOpen && el(window.vaptAdmin.shared.AlertModal, {
        isOpen: alertState.isOpen,
        message: alertState.message,
        type: alertState.type,
        onClose: () => setAlertState({ ...alertState, isOpen: false })
      }),

      // Confirm dialog
      confirmState.isOpen && el(ConfirmDialog, {
        isOpen: confirmState.isOpen,
        message: confirmState.message,
        onConfirm: confirmState.onConfirm,
        onCancel: confirmState.onCancel,
        confirmLabel: confirmState.confirmLabel,
        isDestructive: confirmState.isDestructive
      })
    );
  };

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  window.vaptAdmin.App = App;
})();