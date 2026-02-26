import React, { useState, useRef, useEffect } from 'react';
import './MessageInput.css';

export const MessageInput = ({ onSend, disabled }) => {
  const [message, setMessage] = useState('');
  const inputRef = useRef(null);

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
        placeholder="Type your message... (আপনার মেসেজ লিখুন)"
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
