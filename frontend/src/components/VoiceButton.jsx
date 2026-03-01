import React from 'react';
import './VoiceButton.css';

// Language-specific labels
const labels = {
  en: { recording: 'Listening...', start: 'Click to speak', notSupported: 'Voice not supported' },
  bn: { recording: 'শুনছি...', start: 'কথা বলুন', notSupported: 'ভয়েস সমর্থিত নয়' },
  hi: { recording: 'सुन रहा हूं...', start: 'बोलने के लिए क्लिक करें', notSupported: 'वॉइस समर्थित नहीं है' },
  es: { recording: 'Escuchando...', start: 'Haz clic para hablar', notSupported: 'Voz no compatible' },
  fr: { recording: 'Écoute...', start: 'Cliquez pour parler', notSupported: 'Voix non prise en charge' },
  ar: { recording: 'جاري الاستماع...', start: 'انقر للتحدث', notSupported: 'الصوت غير مدعوم' },
};

export const VoiceButton = ({ 
  isRecording, 
  onStartRecording, 
  onStopRecording, 
  primaryColor = '#007bff',
  language = 'en',
  interimTranscript = '',
  error = null,
  isSupported = true
}) => {
  const handleClick = () => {
    if (!isSupported) return;
    
    if (isRecording) {
      onStopRecording();
    } else {
      onStartRecording();
    }
  };

  const langLabels = labels[language] || labels.en;

  return (
    <div className="voice-button-container">
      <button 
        className={`voice-button ${isRecording ? 'recording' : ''} ${!isSupported ? 'disabled' : ''}`}
        onClick={handleClick}
        style={{ 
          '--primary-color': primaryColor,
          animation: isRecording ? 'pulse 1.5s infinite' : 'none'
        }}
        aria-label={isRecording ? 'Stop recording' : 'Start voice input'}
        title={!isSupported ? langLabels.notSupported : (isRecording ? 'Click to stop' : langLabels.start)}
        disabled={!isSupported}
      >
        <div className="voice-button-icon">
          {isRecording ? (
            <>
              <svg className="mic-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
              </svg>
              <div className="recording-indicator">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </>
          ) : (
            <svg className="mic-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
              <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
            </svg>
          )}
        </div>
        
        {isRecording && (
          <span className="recording-text">{langLabels.recording}</span>
        )}
      </button>
      
      {/* Show interim transcript while recording */}
      {isRecording && interimTranscript && (
        <div className="interim-transcript">
          {interimTranscript}
        </div>
      )}
      
      {/* Show error message */}
      {error && (
        <div className="voice-error">
          {error}
        </div>
      )}
    </div>
  );
};

export default VoiceButton;
