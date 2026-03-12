import React, { useState, useRef, useEffect } from 'react';
import { format } from 'date-fns';
import './MessageList.css';

export const MessageList = ({ messages, isTyping, botTypingText = '...' }) => {
  return (
    <div className="message-list">
      {messages.map((message) => (
        <MessageItem key={message.id} message={message} />
      ))}

      {isTyping && (
        <div className="message-item bot typing">
          <div className="message-avatar bot">
            <span>🏥</span>
          </div>
          <div className="message-content">
            <div className="typing-indicator">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      )}

      <div ref={(el) => el && el.scrollIntoView({ behavior: 'smooth' })} />
    </div>
  );
};

const MessageItem = ({ message }) => {
  const isBot = message.type === 'bot';
  const isEmergency = message.emergency;
  const [isPlaying, setIsPlaying] = useState(false);
  const audioRef = useRef(null);

  // Check if message has audio
  const hasAudio = message.audio_url || message.audio_content;
  
  // Get audio source - prefer base64 content, then URL
  const getAudioSrc = () => {
    if (message.audio_content) {
      return 'data:audio/mp3;base64,' + message.audio_content;
    }
    return message.audio_url || '';
  };

  useEffect(() => {
    if (audioRef.current && hasAudio) {
      const audio = audioRef.current;
      
      const handlePlay = () => setIsPlaying(true);
      const handlePause = () => setIsPlaying(false);
      const handleEnded = () => setIsPlaying(false);
      
      audio.addEventListener('play', handlePlay);
      audio.addEventListener('pause', handlePause);
      audio.addEventListener('ended', handleEnded);
      
      return () => {
        audio.removeEventListener('play', handlePlay);
        audio.removeEventListener('pause', handlePause);
        audio.removeEventListener('ended', handleEnded);
      };
    }
  }, []);

  const handlePlayAudio = () => {
    if (audioRef.current) {
      if (isPlaying) {
        audioRef.current.pause();
      } else {
        audioRef.current.play();
      }
    }
  };

  return (
    <div className={`message-item ${message.type} ${isEmergency ? 'emergency' : ''}`}>
      <div className={`message-avatar ${message.type}`}>
        {message.type === 'bot' ? (
          <span>🏥</span>
        ) : (
          <span>👤</span>
        )}
      </div>
      
      <div className="message-content">
        {isEmergency && (
          <div className="emergency-banner">
            ⚠️ Emergency Detected
          </div>
        )}
        
        <div 
          className="message-text"
          dangerouslySetInnerHTML={{ 
            __html: formatMessageContent(message.content) 
          }}
        />

        {/* Audio player with button for bot responses */}
        {hasAudio && isBot && (
          <div className="audio-player-container">
            <audio 
              ref={audioRef}
              className="message-audio" 
              src={getAudioSrc()}
              preload="none"
            />
            <button 
              className={`audio-play-button ${isPlaying ? 'playing' : ''}`}
              onClick={handlePlayAudio}
              disabled={!getAudioSrc()}
              aria-label={isPlaying ? 'Pause audio' : 'Play audio'}
            >
              <span className="audio-label">
                {isPlaying ? 'Playing...' : 'Listen to response'}
              </span>
            </button>
          </div>
        )}

        {/* Standard audio player for user messages */}
        {hasAudio && !isBot && (
          <audio 
            controls 
            className="message-audio" 
            src={message.audio_url}
          />
        )}

        {message.isVoice && (
          <div className="voice-indicator">
            🎤 Voice input
          </div>
        )}

        <div className="message-time">
          {format(new Date(message.timestamp), 'HH:mm')}
        </div>
      </div>
    </div>
  );
};

// Format message content with basic styling
const formatMessageContent = (content) => {
  // Escape HTML first
  let formatted = escapeHtml(content);
  
  // Format bold text
  formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  
  // Format bullet points
  formatted = formatted.replace(/• /g, '• ');
  formatted = formatted.replace(/\n- /g, '\n• ');
  
  // Convert newlines to <br>
  formatted = formatted.replace(/\n/g, '<br>');
  
  // Format appointment numbers
  formatted = formatted.replace(
    /APT-[A-Z0-9]+/g, 
    '<code class="appointment-number">$&</code>'
  );
  
  return formatted;
};

// Escape HTML special characters
const escapeHtml = (text) => {
  const map = {
    '&': '&',
    '<': '<',
    '>': '>',
    '"': '"',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
};

export default MessageList;
