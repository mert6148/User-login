// Network Manager.js
// This module handles network-related functionalities
// such as managing connections, monitoring status, authentication, and ML controls
// Licensed under the Apache License: http://www.apache.org/licenses/LICENSE-2.0

// API Configuration
const API_CONFIG = {
    baseURL: 'http://localhost:5000/api/v1',
    endpoints: {
        login: '/auth/login',
        logout: '/auth/logout',
        health: '/health',
        users: '/users',
        sessions: '/sessions'
    }
};

// NetworkManager Module
const NetworkManager = {
    currentUser: null,
    sessionId: null,
    isAuthenticated: false,

    init() {
        console.log("Network Manager initialized");
        this.setupEventListeners();
        this.checkExistingSession();
        this.clearCache();
    },

    clearCache() {
        if ('caches' in globalThis) {
    caches.keys().then(function(names) {
        for (let name of names) caches.delete(name);
    });
}
    },

    setupEventListeners() {
        // Login form submission
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Logout button
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.handleLogout());
        }

        // ML Control buttons
        const mlAnalyzeBtn = document.getElementById('ml-analyze-btn');
        if (mlAnalyzeBtn) {
            mlAnalyzeBtn.addEventListener('click', () => this.startMLAnalysis());
        }

        const mlPredictBtn = document.getElementById('ml-predict-btn');
        if (mlPredictBtn) {
            mlPredictBtn.addEventListener('click', () => this.runMLPrediction());
        }

        const mlTrainBtn = document.getElementById('ml-train-btn');
        if (mlTrainBtn) {
            mlTrainBtn.addEventListener('click', () => this.trainMLModel());
        }

        const mlRealtimeBtn = document.getElementById('ml-realtime-btn');
        if (mlRealtimeBtn) {
            mlRealtimeBtn.addEventListener('click', () => this.startRealtimeAnalysis());
        }

        // Forgot password
        const forgotPassword = document.getElementById('forgot-password');
        if (forgotPassword) {
            forgotPassword.addEventListener('click', (e) => {
                e.preventDefault();
                this.showAlert('Şifre sıfırlama özelliği yakında eklenecek.', 'info');
            });
        }
    },

    async handleLogin(event) {
        event.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const rememberMe = document.getElementById('remember-me').checked;

        // ML-based input validation
        const validationResult = this.validateInputs(username, password);
        if (!validationResult.valid) {
            this.showAlert(validationResult.message, 'error');
            return;
        }

        const loginBtn = document.getElementById('login-btn');
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Giriş yapılıyor...';

        try {
            const response = await fetch(`${API_CONFIG.baseURL}${API_CONFIG.endpoints.login}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.currentUser = username;
                this.sessionId = data.session_id;
                this.isAuthenticated = true;

                // Store session if remember me is checked
                if (rememberMe) {
                    localStorage.setItem('sessionId', data.session_id);
                    localStorage.setItem('username', username);
                } else {
                    sessionStorage.setItem('sessionId', data.session_id);
                    sessionStorage.setItem('username', username);
                }

                this.showDashboard();
                this.showAlert('Başarıyla giriş yapıldı!', 'success');
            } else {
                this.showAlert(data.message || 'Giriş başarısız. Lütfen bilgilerinizi kontrol edin.', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            this.showAlert('Bağlantı hatası. Lütfen daha sonra tekrar deneyin.', 'error');
        } finally {
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Giriş Yap';
        }
    },

    async handleLogout() {
        try {
            if (this.sessionId) {
                await fetch(`${API_CONFIG.baseURL}${API_CONFIG.endpoints.logout}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId
                    })
                });
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.currentUser = null;
            this.sessionId = null;
            this.isAuthenticated = false;
            localStorage.removeItem('sessionId');
            localStorage.removeItem('username');
            sessionStorage.removeItem('sessionId');
            sessionStorage.removeItem('username');
            this.showLogin();
        }
    },

    checkExistingSession() {
        const sessionId = localStorage.getItem('sessionId') || sessionStorage.getItem('sessionId');
        const username = localStorage.getItem('username') || sessionStorage.getItem('username');

        if (sessionId && username) {
            // Verify session is still valid
            this.verifySession(sessionId, username);
        } else {
            this.showLogin();
        }
    },

    async verifySession(sessionId, username) {
        try {
            const response = await fetch(`${API_CONFIG.baseURL}${API_CONFIG.endpoints.sessions}`, {
                headers: {
                    'Authorization': `Bearer ${sessionId}`
                }
            });

            if (response.ok) {
                this.currentUser = username;
                this.sessionId = sessionId;
                this.isAuthenticated = true;
                this.showDashboard();
            } else {
                this.showLogin();
            }
        } catch (error) {
            console.error('Session verification error:', error);
            this.showLogin();
        }
    },

    showLogin() {
        document.getElementById('login-container').style.display = 'flex';
        document.getElementById('dashboard-container').style.display = 'none';
    },

    showDashboard() {
        document.getElementById('login-container').style.display = 'none';
        document.getElementById('dashboard-container').style.display = 'block';
        
        // Update user display
        const usernameDisplay = document.getElementById('username-display');
        const welcomeUsername = document.getElementById('welcome-username');
        if (usernameDisplay) usernameDisplay.textContent = this.currentUser;
        if (welcomeUsername) welcomeUsername.textContent = this.currentUser;

        // Initialize dashboard
        this.updateNetworkStatus();
        this.updateServerStatus();
        this.updateSecurityStatus();
        this.updateMLStatus();
        
        // Update ML status periodically
        setInterval(() => {
            this.updateMLStatus();
        }, 30000); // Every 30 seconds
    },

    showAlert(message, type = 'info') {
        const alertBox = document.getElementById('login-alert');
        const alertMessage = document.getElementById('alert-message');
        
        if (alertBox && alertMessage) {
            alertMessage.textContent = message;
            alertBox.className = `alert-box alert-${type}`;
            alertBox.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }
    },

    // ML-based Input Validation
    validateInputs(username, password) {
        // Username validation
        if (!username || username.length < 3) {
            return { valid: false, message: 'Kullanıcı adı en az 3 karakter olmalıdır.' };
        }

        if (!/^\w+$/.test(username)) {
            return { valid: false, message: 'Kullanıcı adı sadece harf, rakam ve alt çizgi içerebilir.' };
        }

        // Password validation
        if (!password || password.length < 8) {
            return { valid: false, message: 'Şifre en az 8 karakter olmalıdır.' };
        }

        // ML-based pattern detection (simple heuristic)
        const suspiciousPatterns = this.detectSuspiciousPatterns(username, password);
        if (suspiciousPatterns.length > 0) {
            return { valid: false, message: 'Güvenlik nedeniyle bu giriş bilgileri kabul edilemez.' };
        }

        return { valid: true };
    },

    detectSuspiciousPatterns(username, password) {
        const patterns = [];
        
        // Check if password contains username
        if (password.toLowerCase().includes(username.toLowerCase())) {
            patterns.push('password_contains_username');
        }

        // Check for common weak passwords
        const commonPasswords = ['password', '12345678', 'qwerty', 'admin'];
        if (commonPasswords.some(p => password.toLowerCase().includes(p))) {
            patterns.push('common_password');
        }

        // Check for sequential characters
        if (this.hasSequentialChars(password)) {
            patterns.push('sequential_chars');
        }

        return patterns;
    },

    hasSequentialChars(str) {
        for (let i = 0; i < str.length - 2; i++) {
            const char1 = str.codePointAt(i);
            const char2 = str.codePointAt(i + 1);
            const char3 = str.codePointAt(i + 2);
            
            if (char1 !== undefined && char2 !== undefined && char3 !== undefined &&
                Math.abs(char2 - char1) === 1 && Math.abs(char3 - char2) === 1) {
                return true;
            }
        }
        return false;
    },

    // Network Status Updates
    async updateNetworkStatus() {
        const statusElement = document.getElementById('network-status');
        if (!statusElement) return;

        try {
            const response = await fetch(`${API_CONFIG.baseURL}${API_CONFIG.endpoints.health}`);
            if (response.ok) {
                statusElement.textContent = 'Bağlı';
                statusElement.className = 'status-online';
            } else {
                statusElement.textContent = 'Bağlantı Hatası';
                statusElement.className = 'status-offline';
            }
        } catch (error) {
            console.error('Network status check failed:', error);
            statusElement.textContent = 'Bağlantı Hatası';
            statusElement.className = 'status-offline';
        }
    },

    async updateServerStatus() {
        const statusElement = document.getElementById('server-status');
        if (!statusElement) return;

        try {
            const response = await fetch(`${API_CONFIG.baseURL}${API_CONFIG.endpoints.health}`);
            const data = await response.json();
            
            if (data.status === 'ok') {
                statusElement.textContent = 'Çalışıyor';
                statusElement.className = 'status-online';
            } else {
                statusElement.textContent = 'Hata';
                statusElement.className = 'status-offline';
            }
        } catch (error) {
            console.error('Server status check failed:', error);
            statusElement.textContent = 'Hata';
            statusElement.className = 'status-offline';
        }
    },

    async updateSecurityStatus() {
        const statusElement = document.getElementById('security-status');
        if (!statusElement) return;

        // ML-based security check
        const securityScore = await this.calculateSecurityScore();
        
        if (securityScore >= 80) {
            statusElement.textContent = 'Güvenli';
            statusElement.className = 'status-online';
        } else if (securityScore >= 50) {
            statusElement.textContent = 'Orta';
            statusElement.className = 'status-warning';
        } else {
            statusElement.textContent = 'Riskli';
            statusElement.className = 'status-offline';
        }
    },

    async calculateSecurityScore() {
        // Simulated ML-based security scoring
        let score = 100;
        
        // Check session validity
        if (!this.isAuthenticated) score -= 50;
        
        // Check HTTPS (if available)
        if (globalThis.location && globalThis.location.protocol !== 'https:') score -= 10;
        
        // Check for suspicious activity patterns
        const suspiciousActivity = this.detectSuspiciousActivity();
        score -= suspiciousActivity * 10;
        
        return Math.max(0, Math.min(100, score));
    },

    detectSuspiciousActivity() {
        // Simple heuristic for suspicious activity detection
        let suspiciousCount = 0;
        
        // Check for rapid requests
        const requestHistory = this.getRequestHistory();
        if (requestHistory.length > 10) {
            suspiciousCount++;
        }
        
        return suspiciousCount;
    },

    getRequestHistory() {
        const history = sessionStorage.getItem('requestHistory');
        return history ? JSON.parse(history) : [];
    },

    // ML Controls
    async startMLAnalysis() {
        const resultsDiv = document.getElementById('ml-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<div class="ml-loading"><i class="fas fa-spinner fa-spin"></i> Analiz başlatılıyor...</div>';
        }

        try {
            // Simulated ML analysis
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            const analysisResult = {
                timestamp: new Date().toLocaleString('tr-TR'),
                networkTraffic: Math.floor(Math.random() * 1000),
                anomalies: Math.floor(Math.random() * 5),
                predictions: ['Normal trafik', 'Düşük risk']
            };

            this.displayMLResults('analysis', analysisResult);
        } catch (error) {
            console.error('ML Analysis error:', error);
            this.showAlert('ML analizi başlatılamadı.', 'error');
        }
    },

    async runMLPrediction() {
        const resultsDiv = document.getElementById('ml-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<div class="ml-loading"><i class="fas fa-spinner fa-spin"></i> Tahmin yapılıyor...</div>';
        }

        try {
            // Simulated ML prediction
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            const predictionResult = {
                timestamp: new Date().toLocaleString('tr-TR'),
                prediction: 'Ağ trafiği normal seviyede',
                confidence: Math.floor(Math.random() * 30 + 70),
                recommendations: ['Sistem durumu iyi', 'Rutin bakım önerilir']
            };

            this.displayMLResults('prediction', predictionResult);
        } catch (error) {
            console.error('ML Prediction error:', error);
            this.showAlert('Tahmin yapılamadı.', 'error');
        }
    },

    async trainMLModel() {
        const resultsDiv = document.getElementById('ml-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<div class="ml-loading"><i class="fas fa-spinner fa-spin"></i> Model eğitiliyor...</div>';
        }

        try {
            // Simulated ML training
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            const trainingResult = {
                timestamp: new Date().toLocaleString('tr-TR'),
                status: 'Tamamlandı',
                accuracy: Math.floor(Math.random() * 10 + 85),
                epochs: 50,
                loss: (Math.random() * 0.1).toFixed(4)
            };

            this.displayMLResults('training', trainingResult);
            this.showAlert('Model eğitimi başarıyla tamamlandı!', 'success');
        } catch (error) {
            console.error('ML Training error:', error);
            this.showAlert('Model eğitimi başarısız oldu.', 'error');
        }
    },

    displayMLResults(type, data) {
        const resultsDiv = document.getElementById('ml-results');
        if (!resultsDiv) return;

        let title;
        if (type === 'analysis') {
            title = 'Ağ Analizi';
        } else if (type === 'prediction') {
            title = 'Tahmin Sonucu';
        } else {
            title = 'Model Eğitimi';
        }

        let html = `<div class="ml-result-card">
            <h4>${title}</h4>
            <p><strong>Zaman:</strong> ${data.timestamp}</p>`;

        if (type === 'analysis') {
            html += `
                <p><strong>Ağ Trafiği:</strong> ${data.networkTraffic} paket/sn</p>
                <p><strong>Anomali Sayısı:</strong> ${data.anomalies}</p>
                <p><strong>Tahminler:</strong> ${data.predictions.join(', ')}</p>
            `;
        } else if (type === 'prediction') {
            html += `
                <p><strong>Tahmin:</strong> ${data.prediction}</p>
                <p><strong>Güven:</strong> %${data.confidence}</p>
                <p><strong>Öneriler:</strong> ${data.recommendations.join(', ')}</p>
            `;
        } else {
            html += `
                <p><strong>Durum:</strong> ${data.status}</p>
                <p><strong>Doğruluk:</strong> %${data.accuracy}</p>
                <p><strong>Epochs:</strong> ${data.epochs}</p>
                <p><strong>Loss:</strong> ${data.loss}</p>
            `;
        }

        html += '</div>';
        resultsDiv.innerHTML = html;
    },

    // ML Configuration Integration
    loadMLConfig() {
        try {
            const savedConfig = localStorage.getItem('ml-config');
            if (savedConfig) {
                return JSON.parse(savedConfig);
            }
        } catch (error) {
            console.error('Failed to load ML config:', error);
        }
        return null;
    },

    updateMLStatus() {
        const mlConfig = this.loadMLConfig();
        const serviceStatus = document.getElementById('ml-service-status');
        const activeModels = document.getElementById('ml-active-models');
        const lastAnalysis = document.getElementById('ml-last-analysis');
        const modelsGrid = document.getElementById('models-status-grid');

        if (serviceStatus) {
            serviceStatus.textContent = mlConfig?.mlServiceEnabled ? 'Aktif' : 'Pasif';
            serviceStatus.className = mlConfig?.mlServiceEnabled ? 'ml-status-value active' : 'ml-status-value inactive';
        }

        if (activeModels) {
            const activeCount = mlConfig?.models?.filter(m => m.status === 'active').length || 2;
            const totalCount = mlConfig?.models?.length || 3;
            activeModels.textContent = `${activeCount}/${totalCount}`;
        }

        if (lastAnalysis) {
            const lastAnalysisTime = localStorage.getItem('ml-last-analysis-time');
            if (lastAnalysisTime) {
                lastAnalysis.textContent = new Date(lastAnalysisTime).toLocaleString('tr-TR');
            } else {
                lastAnalysis.textContent = 'Henüz analiz yapılmadı';
            }
        }

        if (modelsGrid) {
            this.displayModelStatuses(modelsGrid);
        }
    },

    displayModelStatuses(container) {
        const mlConfig = this.loadMLConfig();
        const models = mlConfig?.models || [
            { id: 'anomaly', name: 'Anomali Tespit', status: 'active', accuracy: 94.2 },
            { id: 'traffic', name: 'Trafik Tahmin', status: 'active', accuracy: 87.5 },
            { id: 'security', name: 'Güvenlik Skoru', status: 'inactive', accuracy: 91.8 }
        ];

        container.innerHTML = models.map(model => `
            <div class="model-status-card ${model.status}">
                <div class="model-status-header">
                    <h5>${model.name}</h5>
                    <span class="model-status-badge ${model.status}">${model.status === 'active' ? 'Aktif' : 'Pasif'}</span>
                </div>
                <div class="model-status-info">
                    <p>Doğruluk: <strong>${model.accuracy}%</strong></p>
                </div>
            </div>
        `).join('');
    },

    async startRealtimeAnalysis() {
        const mlConfig = this.loadMLConfig();
        if (!mlConfig?.mlServiceEnabled) {
            this.showAlert('ML servisi devre dışı. Lütfen ayarlardan etkinleştirin.', 'error');
            return;
        }

        const btn = document.getElementById('ml-realtime-btn');
        if (btn) {
            const isRunning = btn.classList.contains('running');
            
            if (isRunning) {
                btn.classList.remove('running');
                btn.innerHTML = '<i class="fas fa-stream"></i><span>Gerçek Zamanlı Analiz</span>';
                this.showAlert('Gerçek zamanlı analiz durduruldu.', 'info');
            } else {
                btn.classList.add('running');
                btn.innerHTML = '<i class="fas fa-stop"></i><span>Durdur</span>';
                this.showAlert('Gerçek zamanlı analiz başlatıldı.', 'success');
                
                // Simulate realtime updates
                this.realtimeAnalysisInterval = setInterval(() => {
                    this.updateRealtimeData();
                }, 5000);
            }
        }
    },

    updateRealtimeData() {
        // Simulate realtime data
        const realtimeData = {
            timestamp: new Date().toLocaleString('tr-TR'),
            networkTraffic: Math.floor(Math.random() * 1000),
            activeConnections: Math.floor(Math.random() * 500),
            anomalies: Math.floor(Math.random() * 5),
            throughput: (Math.random() * 1000).toFixed(2) + ' Mbps'
        };

        localStorage.setItem('ml-last-analysis-time', new Date().toISOString());
        this.updateMLStatus();
        
        // Update results if visible
        const resultsDiv = document.getElementById('ml-results');
        if (resultsDiv?.innerHTML.includes('Gerçek Zamanlı')) {
            this.displayMLResults('analysis', {
                timestamp: realtimeData.timestamp,
                networkTraffic: realtimeData.networkTraffic,
                anomalies: realtimeData.anomalies,
                predictions: ['Canlı veri analizi', 'Normal trafik']
            });
        }
    }
};

// Initialize the Network Manager when the DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NetworkManager.init());
} else {
    NetworkManager.init();
}

export default NetworkManager;