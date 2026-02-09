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
  title = 'Medical Appointment Assistant',
  primaryColor = '#007bff',
  welcomeMessage = 'Hello! I\'m your Medical Appointment Assistant. How can I help you today?'
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
          {/* Header */}
          <div className="chatbot-header" style={{ backgroundColor: primaryColor }}>
            <div className="chatbot-title">
              <span className="chatbot-icon">🏥</span>
              <span>{title}</span>
            </div>
            <button className="chatbot-close" onClick={toggleChat}>
              ✕
            </button>
          </div>

          {/* Messages */}
          <MessageList 
            messages={messages} 
            isTyping={isTyping} 
            botTypingText="Typing..."
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
          style={{ backgroundColor: primaryColor }}
        >
          <span className="chatbot-toggle-icon">💬</span>
        </button>
      )}
    </div>
  );
};

export default ChatBot;
