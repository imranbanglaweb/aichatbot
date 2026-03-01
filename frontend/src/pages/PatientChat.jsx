import React from 'react';
import { useAuth } from '../context/AuthContext';
import DashboardLayout from './DashboardLayout';
import ChatBot from '../components/ChatBot';

const PatientChat = () => {
  const { user } = useAuth();

  return (
    <DashboardLayout title="AI Health Assistant">
      <div className="chat-page-container">
        <div className="chat-page-header">
          <h2>AI Health Assistant</h2>
          <p>Chat with our AI-powered medical assistant for health information and appointment help</p>
        </div>
        
        <div className="chat-widget-container">
          <ChatBot 
            position="relative"
            title="Medical Assistant"
            subtitle="AI Health Assistant"
            welcomeMessage={`Hello ${user?.name || 'Patient'}! I'm your AI medical assistant. How can I help you today? You can ask about:\n\n• Booking appointments\n• Finding doctors\n• Medical information\n• Health tips\n• And more...`}
          />
        </div>
      </div>
    </DashboardLayout>
  );
};

export default PatientChat;
