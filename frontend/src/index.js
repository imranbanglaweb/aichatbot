import React from 'react';
import ReactDOM from 'react-dom/client';
import ChatBot from './components/ChatBot';
import './index.css';

const App = () => {
  return (
    <div className="app">
      <h1>Welcome to Medical Appointment System</h1>
      <p>Click the chat button in the bottom right corner to book an appointment.</p>
      
      <ChatBot 
        apiUrl="http://localhost:8000/api"
        position="bottom-right"
        title="Medical Appointment Assistant"
        primaryColor="#007bff"
      />
    </div>
  );
};

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);
