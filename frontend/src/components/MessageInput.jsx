import React, { useState, useRef, useEffect } from 'react';
import './MessageInput.css';

// Language-specific placeholders
const placeholders = {
  en: 'Type your message...',
  bn: 'আপনার মেসেজ লিখুন...',
  hi: 'अपना संदेश लिखें...',
  es: 'Escribe tu mensaje...',
  fr: 'Écrivez votre message...',
  ar: 'اكتب رسالتك...',
};

export const MessageInput = ({ onSend, disabled, language = 'en', initialValue = '', onClear = null }) => {
  const [message, setMessage] = useState(initialValue);
  const inputRef = useRef(null);

  // Update message when initialValue changes
  useEffect(() => {
    if (initialValue) {
      setMessage(initialValue);
    }
  }, [initialValue]);

  useEffect(() => {
    if (!disabled && inputRef.current) {
      inputRef.current.focus();
    }
  }, [disabled]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (message.trim() && !disabled) {
      onSend(message.trim());
      setMessage('');
      if (onClear) {
        onClear();
      }
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  const handleChange = (e) => {
    setMessage(e.target.value);
  };

  return (
    <form className="message-input-container" onSubmit={handleSubmit}>
      <textarea
        ref={inputRef}
        className="message-input"
        value={message}
        onChange={handleChange}
        onKeyDown={handleKeyDown}
        placeholder={placeholders[language] || placeholders.en}
        disabled={disabled}
        rows={1}
        style={{ 
          height: 'auto',
          minHeight: '44px',
          maxHeight: '120px'
        }}
      />
      
      <button 
        type="submit" 
        className="send-button"
        disabled={!message.trim() || disabled}
        aria-label="Send message"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" />
        </svg>
      </button>
    </form>
  );
};

export default MessageInput;
