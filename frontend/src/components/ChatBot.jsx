import React, { useState, useRef, useEffect, useCallback } from 'react';
import { MessageList } from './MessageList';
import { MessageInput } from './MessageInput';
import { VoiceButton } from './VoiceButton';
import { QuickReplies } from './QuickReplies';
import { useChat } from '../hooks/useChat';
import { useVoice } from '../hooks/useVoice';
import config from '../config';
import './ChatBot.css';

const ChatBot = ({ 
  position = config.ui.position,
  title = config.chat.title,
  subtitle = config.chat.subtitle,
  logoUrl = null,
  primaryColor = config.ui.primaryColor,
  secondaryColor = config.ui.secondaryColor,
  welcomeMessage = config.chat.welcomeMessage
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([
    { id: 'welcome', type: 'bot', content: welcomeMessage, timestamp: new Date() }
  ]);
  const [sessionId, setSessionId] = useState(null);
  const [isTyping, setIsTyping] = useState(false);
  const [quickReplies, setQuickReplies] = useState([]);
  const [selectedLanguage, setSelectedLanguage] = useState('en');
  const [showLanguageMenu, setShowLanguageMenu] = useState(false);
  
  const messagesEndRef = useRef(null);
  const audioPlayerRef = useRef(null);

  const { sendMessage, sendVoice } = useChat(selectedLanguage);
  const { isRecording, startRecording, stopRecording, transcribedText, interimTranscript, error: voiceError, isSupported, clearRecording } = useVoice(selectedLanguage);

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  // Handle transcribed text from Web Speech API
  useEffect(() => {
    if (transcribedText && !isRecording) {
      handleVoiceInput(transcribedText);
      clearRecording();
    }
  }, [transcribedText, isRecording]);

  const handleSendMessage = useCallback(async (text) => {
    if (!text.trim()) return;

    // Add user message
    const userMessage = {
      id: Date.now().toString(),
      type: 'user',
      content: text,
      timestamp: new Date()
    };
    setMessages(prev => [...prev, userMessage]);
    setQuickReplies([]);

    try {
      setIsTyping(true);

      const response = await sendMessage(text, sessionId);

      if (response.session_id && !sessionId) {
        setSessionId(response.session_id);
      }

      // Add bot message
      const botMessage = {
        id: (Date.now() + 1).toString(),
        type: 'bot',
        content: response.response,
        audio_url: response.audio_url,
        audio_content: response.audio_content,
        intent: response.intent,
        emergency: response.emergency,
        extracted_data: response.extracted_data,
        timestamp: new Date()
      };
      setMessages(prev => [...prev, botMessage]);

      // Play audio if available
      if (response.audio_content) {
        const audio = new Audio('data:audio/mp3;base64,' + response.audio_content);
        audio.play().catch(console.error);
      } else if (response.audio_url) {
        audioPlayerRef.current.src = response.audio_url;
        audioPlayerRef.current.play().catch(console.error);
      }

    } catch (error) {
      console.error('Error sending message:', error);
      setMessages(prev => [...prev, {
        id: Date.now().toString(),
        type: 'bot',
        content: 'I apologize, something went wrong. Please try again.',
        timestamp: new Date()
      }]);
    } finally {
      setIsTyping(false);
    }
  }, [sessionId, sendMessage]);

  const handleVoiceInput = useCallback(async (text) => {
    if (!text || !text.trim()) return;

    const trimmedText = text.trim();

    try {
      setIsTyping(true);

      // Use sendMessage with the transcribed text (no need for voice API)
      const response = await sendMessage(trimmedText, sessionId);

      if (response.session_id && !sessionId) {
        setSessionId(response.session_id);
      }

      // Add user message (transcribed text)
      setMessages(prev => [...prev, {
        id: Date.now().toString(),
        type: 'user',
        content: trimmedText,
        isVoice: true,
        timestamp: new Date()
      }]);

      // Add bot response
      const botMessage = {
        id: (Date.now() + 1).toString(),
        type: 'bot',
        content: response.response,
        audio_url: response.audio_url,
        audio_content: response.audio_content,
        intent: response.intent,
        emergency: response.emergency,
        timestamp: new Date()
      };
      setMessages(prev => [...prev, botMessage]);

      // Play audio response if available
      if (response.audio_content) {
        const audio = new Audio('data:audio/mp3;base64,' + response.audio_content);
        audio.play().catch(console.error);
      } else if (response.audio_url && audioPlayerRef.current) {
        audioPlayerRef.current.src = response.audio_url;
        audioPlayerRef.current.play().catch(console.error);
      }

    } catch (error) {
      console.error('Error processing voice:', error);
      setMessages(prev => [...prev, {
        id: Date.now().toString(),
        type: 'bot',
        content: 'I couldn\'t understand that. Please try again.',
        timestamp: new Date()
      }]);
    } finally {
      setIsTyping(false);
    }
  }, [sessionId, sendMessage]);

  const handleQuickReply = (reply) => {
    handleSendMessage(reply);
  };

  const toggleChat = () => {
    setIsOpen(!isOpen);
  };

  const positionStyles = {
    'bottom-right': { bottom: '20px', right: '20px' },
    'bottom-left': { bottom: '20px', left: '20px' },
    'top-right': { top: '20px', right: '20px' },
    'top-left': { top: '20px', left: '20px' }
  };

  return (
    <div className="chatbot-container" style={positionStyles[position]}>
      {/* Hidden audio player */}
      <audio ref={audioPlayerRef} style={{ display: 'none' }} />

      {/* Chat window */}
      {isOpen && (
        <div className="chatbot-window">
          {/* Premium Header */}
          <div 
            className="chatbot-header"
            style={{ 
              background: `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)` 
            }}
          >
            <div className="chatbot-title-wrapper">
              <div className="chatbot-logo">
                {logoUrl ? (
                  <img src={logoUrl} alt="Gulshan Clinic Logo" className="logo-image" />
                ) : (
                  <div className="logo-placeholder">
                    <svg viewBox="0 0 40 40" className="logo-svg">
                      <path d="M20 2C10.06 2 2 10.06 2 20s8.06 18 18 18 18-8.06 18-18S29.94 2 20 2zm0 32c-7.72 0-14-6.28-14-14S12.28 6 20 6s14 6.28 14 14-6.28 14-14 14z" fill="currentColor"/>
                      <path d="M20 8c-6.63 0-12 5.37-12 12s5.37 12 12 12 12-5.37 12-12-5.37-12-12-12zm0 18c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z" fill="currentColor"/>
                      <circle cx="20" cy="20" r="4" fill="currentColor"/>
                    </svg>
                  </div>
                )}
              </div>
              <div className="chatbot-title-info">
                <span className="chatbot-title">{title}</span>
                <span className="chatbot-subtitle">{subtitle}</span>
              </div>
            </div>
            <button className="chatbot-close" onClick={toggleChat}>
              <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
              </svg>
            </button>
            {/* Language Selector */}
            <div className="language-selector-container">
              <button 
                className="language-selector-button"
                onClick={() => setShowLanguageMenu(!showLanguageMenu)}
                title="Select Language"
              >
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                  <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                </svg>
                <span className="language-code">{selectedLanguage.toUpperCase()}</span>
              </button>
              {showLanguageMenu && (
                <div className="language-dropdown">
                  <button 
                    className={`language-option ${selectedLanguage === 'en' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('en'); setShowLanguageMenu(false); }}
                  >
                    🇺🇸 English
                  </button>
                  <button 
                    className={`language-option ${selectedLanguage === 'bn' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('bn'); setShowLanguageMenu(false); }}
                  >
                    🇧🇩 বাংলা
                  </button>
                  <button 
                    className={`language-option ${selectedLanguage === 'hi' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('hi'); setShowLanguageMenu(false); }}
                  >
                    🇮🇳 हिंदी
                  </button>
                  <button 
                    className={`language-option ${selectedLanguage === 'es' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('es'); setShowLanguageMenu(false); }}
                  >
                    🇪🇸 Español
                  </button>
                  <button 
                    className={`language-option ${selectedLanguage === 'fr' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('fr'); setShowLanguageMenu(false); }}
                  >
                    🇫🇷 Français
                  </button>
                  <button 
                    className={`language-option ${selectedLanguage === 'ar' ? 'active' : ''}`}
                    onClick={() => { setSelectedLanguage('ar'); setShowLanguageMenu(false); }}
                  >
                    🇸🇦 العربية
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Hospital Info Banner */}
          <div className="hospital-info-banner">
            <div className="hospital-info-content">
              <span className="hospital-address">📍 {config.chat.clinicAddress}</span>
              <span className="hospital-phone">📞 {config.chat.clinicPhone}</span>
            </div>
          </div>

          {/* Messages */}
          <MessageList 
            messages={messages} 
            isTyping={isTyping} 
            botTypingText="Gulshan Clinic is typing..."
          />

          {/* Quick Replies */}
          {quickReplies.length > 0 && (
            <QuickReplies 
              replies={quickReplies} 
              onReply={handleQuickReply} 
            />
          )}

          {/* Input area */}
          <MessageInput 
            onSend={handleSendMessage}
            disabled={isTyping}
            placeholder="Type your message..."
            language={selectedLanguage}
          />

          {/* Voice button */}
          <VoiceButton 
            isRecording={isRecording}
            onStartRecording={startRecording}
            onStopRecording={stopRecording}
            primaryColor={primaryColor}
            language={selectedLanguage}
            interimTranscript={interimTranscript}
            error={voiceError}
            isSupported={isSupported}
          />
        </div>
      )}

      {/* Chat toggle button */}
      {!isOpen && (
        <button 
          className="chatbot-toggle" 
          onClick={toggleChat}
          style={{ 
            background: `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)` 
          }}
        >
          <div className="toggle-icon-wrapper">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="white">
              <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            </svg>
          </div>
        </button>
      )}
    </div>
  );
};

export default ChatBot;
