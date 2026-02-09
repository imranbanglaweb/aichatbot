import React from 'react';
import './QuickReplies.css';

export const QuickReplies = ({ replies, onReply }) => {
  if (!replies || replies.length === 0) return null;

  return (
    <div className="quick-replies-container">
      {replies.map((reply, index) => (
        <button
          key={index}
          className="quick-reply-button"
          onClick={() => onReply(reply)}
        >
          {reply}
        </button>
      ))}
    </div>
  );
};

// Default quick replies for medical appointment bot
export const defaultQuickReplies = [
  'Book an appointment',
  'Cancel appointment',
  'Check availability',
  'Talk to a doctor',
  'Emergency help',
  'My appointments',
];

export default QuickReplies;
