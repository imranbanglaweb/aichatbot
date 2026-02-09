import React, { useState, useRef, useEffect, useCallback } from 'react';
import { MessageList } from './MessageList';
import { MessageInput } from './MessageInput';
import { VoiceButton } from './VoiceButton';
import { QuickReplies } from './QuickReplies';
import { useChat } from '../hooks/useChat';
import { useVoice } from '../hooks/useVoice';
import './ChatBot.css';

const ChatBot = ({ 
  apiUrl = 'http://localhost:8000/api',
  position = 'bottom-right',
  title = 'Gulshan Clinic',
  subtitle = 'Medical Appointment Assistant',
  logoUrl = null,
  primaryColor = '#1a5f7a',
  secondaryColor = '#159895',
  welcomeMessage = 'Hello! Welcome to Gulshan Clinic. I\'m your Medical Appointment Assistant. How can I help you today?'
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([
    { id: 'welcome', type: 'bot', content: welcomeMessage, timestamp: new Date() }
  ]);
  const [sessionId, setSessionId] = useState(null);
  const [isTyping, setIsTyping] = useState(false);
  const [quickReplies, setQuickReplies] = useState([]);
  
  const messagesEndRef = useRef(null);
  const audioPlayerRef = useRef(null);

  const { sendMessage, sendVoice } = useChat(apiUrl);
  const { isRecording, startRecording, stopRecording, audioBlob } = useVoice();

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  // Handle audio blob for voice processing
  useEffect(() => {
    if (audioBlob) {
      handleVoiceInput();
    }
  }, [audioBlob]);

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
        intent: response.intent,
        emergency: response.emergency,
        extracted_data: response.extracted_data,
        timestamp: new Date()
      };
      setMessages(prev => [...prev, botMessage]);

      // Play audio if available
      if (response.audio_url && audioPlayerRef.current) {
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

  const handleVoiceInput = useCallback(async () => {
    if (!audioBlob) return;

    try {
      setIsTyping(true);

      const response = await sendVoice(audioBlob, sessionId);

      if (response.session_id && !sessionId) {
        setSessionId(response.session_id);
      }

      // Add user message (transcribed text)
      if (response.text) {
        setMessages(prev => [...prev, {
          id: Date.now().toString(),
          type: 'user',
          content: response.text,
          isVoice: true,
          timestamp: new Date()
        }]);
      }

      // Add bot response
      const botMessage = {
        id: (Date.now() + 1).toString(),
        type: 'bot',
        content: response.text ? response.response : response.response,
        audio_url: response.audio_url,
        intent: response.intent,
        emergency: response.emergency,
        timestamp: new Date()
      };
      setMessages(prev => [...prev, botMessage]);

      // Play audio response
      if (response.audio_url && audioPlayerRef.current) {
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
  }, [audioBlob, sessionId, sendVoice]);

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
          </div>

          {/* Hospital Info Banner */}
          <div className="hospital-info-banner">
            <div className="hospital-info-content">
              <span className="hospital-address">📍 GP-GA-13, Pragati Sharani, Gulshan-2, Dhaka-1212</span>
              <span className="hospital-phone">📞 09606-991133</span>
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
          />

          {/* Voice button */}
          <VoiceButton 
            isRecording={isRecording}
            onStartRecording={startRecording}
            onStopRecording={stopRecording}
            primaryColor={primaryColor}
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
