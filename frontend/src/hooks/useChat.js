import { useCallback, useState } from 'react';
import config from '../config';

export const useChat = (language = 'en') => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const apiUrl = config.api.baseUrl;

  const sendMessage = useCallback(async (message, sessionId = null) => {
    setIsLoading(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('message', message);
      formData.append('language', language);
      
      if (sessionId) {
        formData.append('session_id', sessionId);
      }

      const response = await fetch(`${apiUrl}/chat/message`, {
        method: 'POST',
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to send message');
      }

      const data = await response.json();
      return data;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, [apiUrl, language]);

  const sendVoice = useCallback(async (audioBlob, sessionId = null) => {
    setIsLoading(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('audio', audioBlob, 'voice.webm');
      formData.append('language', language);
      
      if (sessionId) {
        formData.append('session_id', sessionId);
      }

      const response = await fetch(`${apiUrl}/chat/voice/stt`, {
        method: 'POST',
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to process voice');
      }

      const data = await response.json();
      return data;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, [apiUrl, language]);

  const getChatHistory = useCallback(async (sessionId) => {
    try {
      const response = await fetch(`${apiUrl}/chat/history/${sessionId}`);
      
      if (!response.ok) {
        throw new Error('Failed to fetch chat history');
      }

      return await response.json();
    } catch (err) {
      setError(err.message);
      throw err;
    }
  }, [apiUrl]);

  const endChat = useCallback(async (sessionId) => {
    try {
      const response = await fetch(`${apiUrl}/chat/end/${sessionId}`, {
        method: 'POST',
      });

      if (!response.ok) {
        throw new Error('Failed to end chat');
      }

      return await response.json();
    } catch (err) {
      setError(err.message);
      throw err;
    }
  }, [apiUrl]);

  return {
    sendMessage,
    sendVoice,
    getChatHistory,
    endChat,
    isLoading,
    error,
  };
};

export default useChat;
