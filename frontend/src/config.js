/**
 * Frontend Environment Configuration
 * This file centralizes all API URLs and configuration
 * In production, these should be set via environment variables
 */

// Get API URL from environment or use default
const getApiUrl = () => {
  // Check if running in production (build time)
  if (process.env.NODE_ENV === 'production') {
    return process.env.REACT_APP_API_URL || 'https://your-production-domain.com/api';
  }
  // Development URL
  return process.env.REACT_APP_API_URL || 'http://localhost:8000/api';
};

// Configuration object
const config = {
  // API Configuration
  api: {
    baseUrl: getApiUrl(),
    timeout: 30000, // 30 seconds
  },

  // Chat Configuration
  chat: {
    welcomeMessage: 'Hello! Welcome to Gulshan Clinic. I\'m your Medical Appointment Assistant. How can I help you today?',
    title: 'Gulshan Clinic',
    subtitle: 'Medical Appointment Assistant',
    clinicAddress: 'GP-GA-13, Pragati Sharani, Gulshan-2, Dhaka-1212',
    clinicPhone: '09606-991133',
  },

  // UI Configuration
  ui: {
    primaryColor: '#1a5f7a',
    secondaryColor: '#159895',
    position: 'bottom-right',
  },

  // Feature Flags
  features: {
    voiceEnabled: true,
    multiLanguage: true,
    quickReplies: true,
    audioPlayback: true,
  },

  // Supported Languages
  languages: [
    { code: 'en', name: 'English', flag: '🇺🇸' },
    { code: 'bn', name: 'বাংলা', flag: '🇧🇩' },
    { code: 'hi', name: 'हिंदी', flag: '🇮🇳' },
    { code: 'es', name: 'Español', flag: '🇪🇸' },
    { code: 'fr', name: 'Français', flag: '🇫🇷' },
    { code: 'ar', name: 'العربية', flag: '🇸🇦' },
  ],
};

export default config;
