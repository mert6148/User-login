// ML Configuration Manager
// Handles ML configuration settings and model management

const MLConfig = {
    config: {
        mlServiceEnabled: true,
        autoAnalysisEnabled: true,
        analysisInterval: 30,
        mlThreadCount: 4,
        dataRetentionDays: 30,
        maxDatasetSize: 1000,
        dataEncryptionEnabled: true,
        anomalyThreshold: 75,
        confidenceLevel: 85,
        predictionWindow: 6,
        sampleSize: 10000,
        deepAnalysisEnabled: false,
        noiseFilterEnabled: true,
        noiseThreshold: 10,
        mlInputValidation: true,
        suspiciousPatternDetection: true,
        securityScoreThreshold: 70,
        autoThreatBlocking: false,
        maxLoginAttempts: 5,
        emailNotifications: false,
        notificationEmail: '',
        anomalyNotifications: true,
        trainingNotifications: true,
        securityAlerts: true
    },

    models: [
        {
            id: 'anomaly',
            name: 'Anomali Tespit Modeli',
            version: 'v2.1.0',
            accuracy: 94.2,
            status: 'active',
            lastUpdate: '2024-01-15',
            algorithm: 'Isolation Forest'
        },
        {
            id: 'traffic',
            name: 'Trafik Tahmin Modeli',
            version: 'v1.8.5',
            accuracy: 87.5,
            status: 'active',
            lastUpdate: '2024-01-10',
            algorithm: 'LSTM Neural Network'
        },
        {
            id: 'security',
            name: 'Güvenlik Skoru Modeli',
            version: 'v1.2.3',
            accuracy: 91.8,
            status: 'inactive',
            lastUpdate: '2023-12-20',
            algorithm: 'Random Forest'
        }
    ],

    init() {
        this.loadConfig();
        this.setupEventListeners();
        this.updateUI();
        this.setupTabs();
    },

    loadConfig() {
        const savedConfig = localStorage.getItem('ml-config');
        if (savedConfig) {
            try {
                this.config = { ...this.config, ...JSON.parse(savedConfig) };
            } catch (error) {
                console.error('Failed to load ML config:', error);
            }
        }
    },

    saveConfig() {
        try {
            localStorage.setItem('ml-config', JSON.stringify(this.config));
            this.showAlert('Ayarlar başarıyla kaydedildi!', 'success');
            return true;
        } catch (error) {
            console.error('Failed to save ML config:', error);
            this.showAlert('Ayarlar kaydedilemedi.', 'error');
            return false;
        }
    },

    setupEventListeners() {
        // Save button
        const saveBtn = document.getElementById('save-config-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveAllSettings());
        }

        // Reset button
        const resetBtn = document.getElementById('reset-config-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetToDefaults());
        }

        // Export button
        const exportBtn = document.getElementById('export-config-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportConfig());
        }

        // Import button
        const importBtn = document.getElementById('import-config-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.importConfig());
        }

        // Range inputs
        this.setupRangeInputs();

        // Model upload
        const uploadArea = document.getElementById('model-upload-area');
        const fileInput = document.getElementById('model-file-input');
        
        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', () => fileInput.click());
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleModelUpload(files[0]);
                }
            });
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleModelUpload(e.target.files[0]);
                }
            });
        }

        // Quick config checkboxes
        const quickConfigs = ['quick-auto-analysis', 'quick-deep-analysis', 'quick-anomaly-alerts', 'quick-realtime-monitoring'];
        quickConfigs.forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                checkbox.addEventListener('change', (e) => {
                    this.updateQuickConfig(id, e.target.checked);
                });
            }
        });
    },

    setupRangeInputs() {
        const rangeInputs = [
            { id: 'anomaly-threshold', valueId: 'anomaly-threshold-value', key: 'anomalyThreshold' },
            { id: 'confidence-level', valueId: 'confidence-level-value', key: 'confidenceLevel' },
            { id: 'noise-threshold', valueId: 'noise-threshold-value', key: 'noiseThreshold' },
            { id: 'security-score-threshold', valueId: 'security-score-threshold-value', key: 'securityScoreThreshold' }
        ];

        rangeInputs.forEach(({ id, valueId, key }) => {
            const input = document.getElementById(id);
            const valueDisplay = document.getElementById(valueId);
            
            if (input && valueDisplay) {
                input.value = this.config[key];
                valueDisplay.textContent = `${this.config[key]}%`;
                
                input.addEventListener('input', (e) => {
                    const value = e.target.value;
                    valueDisplay.textContent = `${value}%`;
                    this.config[key] = parseInt(value);
                });
            }
        });
    },

    setupTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.config-tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');

                // Remove active class from all tabs and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                button.classList.add('active');
                const targetContent = document.getElementById(`tab-${targetTab}`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    },

    updateUI() {
        // Update all form inputs with current config values
        Object.keys(this.config).forEach(key => {
            const element = document.getElementById(this.getInputId(key));
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = this.config[key];
                } else {
                    element.value = this.config[key];
                }
            }
        });

        // Update quick config
        const quickAutoAnalysis = document.getElementById('quick-auto-analysis');
        const quickDeepAnalysis = document.getElementById('quick-deep-analysis');
        const quickAnomalyAlerts = document.getElementById('quick-anomaly-alerts');
        const quickRealtimeMonitoring = document.getElementById('quick-realtime-monitoring');

        if (quickAutoAnalysis) quickAutoAnalysis.checked = this.config.autoAnalysisEnabled;
        if (quickDeepAnalysis) quickDeepAnalysis.checked = this.config.deepAnalysisEnabled;
        if (quickAnomalyAlerts) quickAnomalyAlerts.checked = this.config.anomalyNotifications;
        if (quickRealtimeMonitoring) quickRealtimeMonitoring.checked = this.config.autoThreatBlocking;
    },

    getInputId(key) {
        const mapping = {
            mlServiceEnabled: 'ml-service-enabled',
            autoAnalysisEnabled: 'auto-analysis-enabled',
            analysisInterval: 'analysis-interval',
            mlThreadCount: 'ml-thread-count',
            dataRetentionDays: 'data-retention-days',
            maxDatasetSize: 'max-dataset-size',
            dataEncryptionEnabled: 'data-encryption-enabled',
            deepAnalysisEnabled: 'deep-analysis-enabled',
            noiseFilterEnabled: 'noise-filter-enabled',
            mlInputValidation: 'ml-input-validation',
            suspiciousPatternDetection: 'suspicious-pattern-detection',
            autoThreatBlocking: 'auto-threat-blocking',
            maxLoginAttempts: 'max-login-attempts',
            emailNotifications: 'email-notifications',
            notificationEmail: 'notification-email',
            anomalyNotifications: 'anomaly-notifications',
            trainingNotifications: 'training-notifications',
            securityAlerts: 'security-alerts'
        };
        return mapping[key] || key;
    },

    saveAllSettings() {
        // Collect all settings from form
        this.config.mlServiceEnabled = document.getElementById('ml-service-enabled')?.checked ?? this.config.mlServiceEnabled;
        this.config.autoAnalysisEnabled = document.getElementById('auto-analysis-enabled')?.checked ?? this.config.autoAnalysisEnabled;
        this.config.analysisInterval = parseInt(document.getElementById('analysis-interval')?.value) || this.config.analysisInterval;
        this.config.mlThreadCount = parseInt(document.getElementById('ml-thread-count')?.value) || this.config.mlThreadCount;
        this.config.dataRetentionDays = parseInt(document.getElementById('data-retention-days')?.value) || this.config.dataRetentionDays;
        this.config.maxDatasetSize = parseInt(document.getElementById('max-dataset-size')?.value) || this.config.maxDatasetSize;
        this.config.dataEncryptionEnabled = document.getElementById('data-encryption-enabled')?.checked ?? this.config.dataEncryptionEnabled;
        this.config.deepAnalysisEnabled = document.getElementById('deep-analysis-enabled')?.checked ?? this.config.deepAnalysisEnabled;
        this.config.noiseFilterEnabled = document.getElementById('noise-filter-enabled')?.checked ?? this.config.noiseFilterEnabled;
        this.config.mlInputValidation = document.getElementById('ml-input-validation')?.checked ?? this.config.mlInputValidation;
        this.config.suspiciousPatternDetection = document.getElementById('suspicious-pattern-detection')?.checked ?? this.config.suspiciousPatternDetection;
        this.config.autoThreatBlocking = document.getElementById('auto-threat-blocking')?.checked ?? this.config.autoThreatBlocking;
        this.config.maxLoginAttempts = parseInt(document.getElementById('max-login-attempts')?.value) || this.config.maxLoginAttempts;
        this.config.emailNotifications = document.getElementById('email-notifications')?.checked ?? this.config.emailNotifications;
        this.config.notificationEmail = document.getElementById('notification-email')?.value || this.config.notificationEmail;
        this.config.anomalyNotifications = document.getElementById('anomaly-notifications')?.checked ?? this.config.anomalyNotifications;
        this.config.trainingNotifications = document.getElementById('training-notifications')?.checked ?? this.config.trainingNotifications;
        this.config.securityAlerts = document.getElementById('security-alerts')?.checked ?? this.config.securityAlerts;

        // Get range values
        this.config.anomalyThreshold = parseInt(document.getElementById('anomaly-threshold')?.value) || this.config.anomalyThreshold;
        this.config.confidenceLevel = parseInt(document.getElementById('confidence-level')?.value) || this.config.confidenceLevel;
        this.config.noiseThreshold = parseInt(document.getElementById('noise-threshold')?.value) || this.config.noiseThreshold;
        this.config.securityScoreThreshold = parseInt(document.getElementById('security-score-threshold')?.value) || this.config.securityScoreThreshold;

        // Get select values
        this.config.predictionWindow = parseInt(document.getElementById('prediction-window')?.value) || this.config.predictionWindow;
        this.config.sampleSize = parseInt(document.getElementById('sample-size')?.value) || this.config.sampleSize;

        this.saveConfig();
    },

    resetToDefaults() {
        if (confirm('Tüm ayarları varsayılan değerlere döndürmek istediğinizden emin misiniz?')) {
            localStorage.removeItem('ml-config');
            this.config = {
                mlServiceEnabled: true,
                autoAnalysisEnabled: true,
                analysisInterval: 30,
                mlThreadCount: 4,
                dataRetentionDays: 30,
                maxDatasetSize: 1000,
                dataEncryptionEnabled: true,
                anomalyThreshold: 75,
                confidenceLevel: 85,
                predictionWindow: 6,
                sampleSize: 10000,
                deepAnalysisEnabled: false,
                noiseFilterEnabled: true,
                noiseThreshold: 10,
                mlInputValidation: true,
                suspiciousPatternDetection: true,
                securityScoreThreshold: 70,
                autoThreatBlocking: false,
                maxLoginAttempts: 5,
                emailNotifications: false,
                notificationEmail: '',
                anomalyNotifications: true,
                trainingNotifications: true,
                securityAlerts: true
            };
            this.updateUI();
            this.showAlert('Ayarlar varsayılan değerlere döndürüldü.', 'info');
        }
    },

    exportConfig() {
        const configData = {
            config: this.config,
            models: this.models,
            exportDate: new Date().toISOString()
        };
        
        const blob = new Blob([JSON.stringify(configData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ml-config-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showAlert('Konfigürasyon dışa aktarıldı!', 'success');
    },

    importConfig() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    try {
                        const imported = JSON.parse(event.target.result);
                        if (imported.config) {
                            this.config = { ...this.config, ...imported.config };
                            if (imported.models) {
                                this.models = imported.models;
                            }
                            this.saveConfig();
                            this.updateUI();
                            this.showAlert('Konfigürasyon başarıyla içe aktarıldı!', 'success');
                        } else {
                            this.showAlert('Geçersiz konfigürasyon dosyası.', 'error');
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        this.showAlert('Dosya okunamadı.', 'error');
                    }
                };
                reader.readAsText(file);
            }
        };
        input.click();
    },

    updateQuickConfig(id, value) {
        const mapping = {
            'quick-auto-analysis': 'autoAnalysisEnabled',
            'quick-deep-analysis': 'deepAnalysisEnabled',
            'quick-anomaly-alerts': 'anomalyNotifications',
            'quick-realtime-monitoring': 'autoThreatBlocking'
        };

        const key = mapping[id];
        if (key) {
            this.config[key] = value;
            this.saveConfig();
        }
    },

    handleModelUpload(file) {
        if (file.size > 500 * 1024 * 1024) {
            this.showAlert('Dosya boyutu 500 MB\'dan büyük olamaz.', 'error');
            return;
        }

        const allowedExtensions = ['.h5', '.pkl', '.onnx', '.pb'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(fileExtension)) {
            this.showAlert('Desteklenmeyen dosya formatı.', 'error');
            return;
        }

        this.showAlert('Model yükleniyor...', 'info');
        // Simulate upload
        setTimeout(() => {
            this.showAlert('Model başarıyla yüklendi!', 'success');
        }, 2000);
    },

    reloadModel(modelId) {
        const model = this.models.find(m => m.id === modelId);
        if (model) {
            this.showAlert(`${model.name} yenileniyor...`, 'info');
            setTimeout(() => {
                this.showAlert(`${model.name} başarıyla yenilendi!`, 'success');
            }, 1500);
        }
    },

    testModel(modelId) {
        const model = this.models.find(m => m.id === modelId);
        if (model) {
            this.showAlert(`${model.name} test ediliyor...`, 'info');
            setTimeout(() => {
                this.showAlert(`${model.name} test sonucu: Başarılı (Doğruluk: ${model.accuracy}%)`, 'success');
            }, 2000);
        }
    },

    deleteModel(modelId) {
        const model = this.models.find(m => m.id === modelId);
        if (model && confirm(`${model.name} modelini silmek istediğinizden emin misiniz?`)) {
            this.models = this.models.filter(m => m.id !== modelId);
            this.showAlert(`${model.name} silindi.`, 'success');
        }
    },

    activateModel(modelId) {
        const model = this.models.find(m => m.id === modelId);
        if (model) {
            model.status = 'active';
            this.showAlert(`${model.name} aktifleştirildi.`, 'success');
        }
    },

    showAlert(message, type = 'info') {
        const alertBox = document.getElementById('config-alert');
        const alertMessage = document.getElementById('config-alert-message');
        
        if (alertBox && alertMessage) {
            alertMessage.textContent = message;
            alertBox.className = `alert-box alert-${type}`;
            alertBox.style.display = 'flex';
            
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MLConfig.init());
} else {
    MLConfig.init();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MLConfig;
}

