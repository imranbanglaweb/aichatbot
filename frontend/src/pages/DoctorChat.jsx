import React from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import ChatBot from '../components/ChatBot';

const DoctorChat = () => {
  const { user } = useAuth();

  return (
    <DashboardLayout title="AI Assistant">
      <div className="chat-page-container">
        <div className="chat-page-header">
          <h2>AI Assistant</h2>
          <p>Chat with our AI assistant for appointment management and patient information</p>
        </div>
        
        <div className="chat-widget-container">
          <ChatBot 
            position="relative"
            title="Doctor Assistant"
            subtitle="AI Practice Assistant"
            welcomeMessage={`Hello Dr. ${user?.name || ''}! I'm your AI assistant. I can help you with:\n\n• Patient appointment queries\n• Schedule management\n• Medical information lookup\n• Patient history summaries\n• And more...`}
          />
        </div>
      </div>
    </DashboardLayout>
  );
};

export default DoctorChat;
