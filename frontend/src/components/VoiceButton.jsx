import React from 'react';
import './VoiceButton.css';

export const VoiceButton = ({ 
  isRecording, 
  onStartRecording, 
  onStopRecording, 
  primaryColor = '#007bff' 
}) => {
  const handleClick = () => {
    if (isRecording) {
      onStopRecording();
    } else {
      onStartRecording();
    }
  };

  return (
    <button 
      className={`voice-button ${isRecording ? 'recording' : ''}`}
      onClick={handleClick}
      style={{ 
        '--primary-color': primaryColor,
        animation: isRecording ? 'pulse 1.5s infinite' : 'none'
      }}
      aria-label={isRecording ? 'Stop recording' : 'Start voice input'}
      title={isRecording ? 'Click to stop' : 'Click to speak'}
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
        <span className="recording-text">Listening...</span>
      )}
    </button>
  );
};

export default VoiceButton;
