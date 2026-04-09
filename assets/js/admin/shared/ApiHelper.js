// API Helper for VAPT Secure Admin
// Centralized API calls with error handling

(function () {
  const apiFetch = wp.apiFetch;
  const { __, sprintf } = wp.i18n || {};

  class ApiHelper {
    static settings = null;

    static init(settings) {
      this.settings = settings;
      this.applyRestHotpatch();
    }

    static applyRestHotpatch() {
      // 🛡️ GLOBAL REST HOTPATCH (v3.8.16)
      // Replaces the global wp.apiFetch to catch 404s from any component
      if (wp.apiFetch && !wp.apiFetch.__vaptsecure_patched && this.settings) {
        let localBroken = localStorage.getItem('vaptsecure_rest_broken') === '1';
        const originalApiFetch = wp.apiFetch;

        const patchedApiFetch = (args) => {
          // 🛡️ AUTH PERI-FIX: Ensure Nonce is present for non-GET requests
          if (this.settings.nonce && args.method && args.method !== 'GET') {
            args.headers = Object.assign({}, args.headers || {}, { 'X-WP-Nonce': this.settings.nonce });
          }

          const getFallbackUrl = (pathOrUrl) => {
            if (!pathOrUrl) return null;
            const path = typeof pathOrUrl === 'string' && pathOrUrl.includes('/wp-json/')
              ? pathOrUrl.split('/wp-json/')[1]
              : pathOrUrl;
            const cleanHome = this.settings.homeUrl.replace(/\/$/, '');
            const cleanPath = path.replace(/^\//, '').split('?')[0];
            const queryParams = path.includes('?') ? '&' + path.split('?')[1] : '';
            const nonceParam = this.settings.nonce ? '&_wpnonce=' + this.settings.nonce : '';
            return cleanHome + '/?rest_route=/' + cleanPath + queryParams + nonceParam;
          };

          // 🛡️ Pre-emptive Fallback if we already know REST is broken
          if (localBroken && (args.path || args.url) && this.settings.homeUrl) {
            const fallbackUrl = getFallbackUrl(args.path || args.url);
            if (fallbackUrl) {
              const fallbackArgs = Object.assign({}, args, { url: fallbackUrl });
              delete fallbackArgs.path;
              return originalApiFetch(fallbackArgs);
            }
          }

          return originalApiFetch(args).catch(err => {
            const status = err.status || (err.data && err.data.status);
            // 🛡️ Trigger fallback on 403/404 OR invalid_json (common when server returns HTML for error)
            const isFallbackTrigger = status === 404 || status === 403 || err.code === 'rest_no_route' || err.code === 'invalid_json';

            if (isFallbackTrigger && (args.path || args.url) && this.settings.homeUrl) {
              const fallbackUrl = getFallbackUrl(args.path || args.url);
              if (!fallbackUrl) throw err;

              if (!localBroken) {
                localStorage.setItem('vaptsecure_rest_broken', '1');
                localBroken = true;
              }

              const fallbackArgs = Object.assign({}, args, { url: fallbackUrl });
              delete fallbackArgs.path;
              return originalApiFetch(fallbackArgs);
            }
            throw err;
          });
        };

        wp.apiFetch = patchedApiFetch;
        wp.apiFetch.__vaptsecure_patched = true;
      }
    }

    static async getFeatures(file = null) {
      try {
        const params = file ? { file } : {};
        const response = await apiFetch({
          path: '/vaptsecure/v1/features',
          method: 'GET',
          data: params
        });
        return response.data || response;
      } catch (error) {
        console.error('Error fetching features:', error);
        throw error;
      }
    }

    static async updateFeature(featureKey, data) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/features/update',
          method: 'POST',
          data: { feature_key: featureKey, ...data }
        });
        return response;
      } catch (error) {
        console.error('Error updating feature:', error);
        throw error;
      }
    }

    static async transitionFeature(featureKey, status, note = '') {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/features/transition',
          method: 'POST',
          data: { feature_key: featureKey, status, note }
        });
        return response;
      } catch (error) {
        console.error('Error transitioning feature:', error);
        throw error;
      }
    }

    static async verifyImplementation(featureKey) {
      try {
        const response = await apiFetch({
          path: `/vaptsecure/v1/features/${featureKey}/verify`,
          method: 'POST'
        });
        return response;
      } catch (error) {
        console.error('Error verifying implementation:', error);
        throw error;
      }
    }

    static async getDomains() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/domains',
          method: 'GET'
        });
        return response.data || response;
      } catch (error) {
        console.error('Error fetching domains:', error);
        throw error;
      }
    }

    static async updateDomain(domain, data) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/domains/update',
          method: 'POST',
          data: { domain, ...data }
        });
        return response;
      } catch (error) {
        console.error('Error updating domain:', error);
        throw error;
      }
    }

    static async updateDomainFeatures(domain, features) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/domains/features',
          method: 'POST',
          data: { domain, features }
        });
        return response;
      } catch (error) {
        console.error('Error updating domain features:', error);
        throw error;
      }
    }

    static async deleteDomain(domain) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/domains/delete',
          method: 'DELETE',
          data: { domain }
        });
        return response;
      } catch (error) {
        console.error('Error deleting domain:', error);
        throw error;
      }
    }

    static async batchDeleteDomains(domains) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/domains/batch-delete',
          method: 'POST',
          data: { domains }
        });
        return response;
      } catch (error) {
        console.error('Error batch deleting domains:', error);
        throw error;
      }
    }

    static async getDataFiles() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/data-files/all',
          method: 'GET'
        });
        return response.data?.files || [];
      } catch (error) {
        console.error('Error fetching data files:', error);
        throw error;
      }
    }

    static async uploadJsonFile(file) {
      try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await apiFetch({
          path: '/vaptsecure/v1/upload-json',
          method: 'POST',
          body: formData
        });
        return response;
      } catch (error) {
        console.error('Error uploading JSON file:', error);
        throw error;
      }
    }

    static async generateBuild(data) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/build/generate',
          method: 'POST',
          data
        });
        return response;
      } catch (error) {
        console.error('Error generating build:', error);
        throw error;
      }
    }

    static async getGlobalEnforcement() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/settings/enforcement',
          method: 'GET'
        });
        return response.data?.enforcement || { enabled: true };
      } catch (error) {
        console.error('Error fetching global enforcement:', error);
        throw error;
      }
    }

    static async updateGlobalEnforcement(data) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/settings/enforcement',
          method: 'POST',
          data: { enforcement: data }
        });
        return response;
      } catch (error) {
        console.error('Error updating global enforcement:', error);
        throw error;
      }
    }

    static async getSecurityStats() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/security/stats',
          method: 'GET'
        });
        return response.data;
      } catch (error) {
        console.error('Error fetching security stats:', error);
        throw error;
      }
    }

    static async getSecurityLogs(params = {}) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/security/logs',
          method: 'GET',
          data: params
        });
        return response.data;
      } catch (error) {
        console.error('Error fetching security logs:', error);
        throw error;
      }
    }

    static async clearCache(cacheType = 'all') {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/clear-cache',
          method: 'POST',
          data: { cache_type: cacheType }
        });
        return response;
      } catch (error) {
        console.error('Error clearing cache:', error);
        throw error;
      }
    }

    static async getLicenseStatus(refresh = false) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/license/status' + (refresh ? '?refresh=true' : ''),
          method: 'GET'
        });
        return response.data;
      } catch (error) {
        console.error('Error fetching license status:', error);
        throw error;
      }
    }

    static async forceLicenseCheck(licenseKey = null) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/license/check',
          method: 'POST',
          data: licenseKey ? { license_key: licenseKey } : {}
        });
        return response;
      } catch (error) {
        console.error('Error forcing license check:', error);
        throw error;
      }
    }

    static async restoreLicenseCache(backupKey) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/license/restore',
          method: 'POST',
          data: { backup_key: backupKey }
        });
        return response;
      } catch (error) {
        console.error('Error restoring license cache:', error);
        throw error;
      }
    }

    static async batchRevertToDraft(featureKeys, includeBroken = false, includeRelease = false) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/features/batch-revert',
          method: 'POST',
          data: { feature_keys: featureKeys, include_broken: includeBroken, include_release: includeRelease }
        });
        return response;
      } catch (error) {
        console.error('Error batch reverting features:', error);
        throw error;
      }
    }

    static async previewRevertToDraft() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/features/preview-revert',
          method: 'GET'
        });
        return response.data;
      } catch (error) {
        console.error('Error previewing revert:', error);
        throw error;
      }
    }

    static async getAssignees() {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/assignees',
          method: 'GET'
        });
        return response.data;
      } catch (error) {
        console.error('Error fetching assignees:', error);
        throw error;
      }
    }

    static async updateAssignment(featureKey, assignedTo) {
      try {
        const response = await apiFetch({
          path: '/vaptsecure/v1/features/assign',
          method: 'POST',
          data: { feature_key: featureKey, assigned_to: assignedTo }
        });
        return response;
      } catch (error) {
        console.error('Error updating assignment:', error);
        throw error;
      }
    }
  }

  // Export to global namespace
  if (!window.vaptAdmin) window.vaptAdmin = {};
  window.vaptAdmin.ApiHelper = ApiHelper;
  window.vaptAdmin.applyRestHotpatch = (settings) => ApiHelper.init(settings);
})();