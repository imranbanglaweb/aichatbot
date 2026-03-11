import { useState, useRef, useCallback } from 'react';
import api from '../services/api';

// Map internal language codes to API language codes
const languageMap = {
  en: 'en',
  bn: 'bn',
  hi: 'hi',
  es: 'es',
  fr: 'fr',
  ar: 'ar',
  de: 'de',
  zh: 'zh',
  ja: 'ja',
};

// Check if MediaRecorder is available
const isMediaRecorderSupported = typeof MediaRecorder !== 'undefined';

export const useVoice = (language = 'en') => {
  const [isRecording, setIsRecording] = useState(false);
  const [transcribedText, setTranscribedText] = useState('');
  const [recordingTime, setRecordingTime] = useState(0);
  const [error, setError] = useState(null);
  const [isTranscribing, setIsTranscribing] = useState(false);
  
  const mediaRecorderRef = useRef(null);
  const streamRef = useRef(null);
  const audioChunksRef = useRef([]);
  const timerRef = useRef(null);

  const startRecording = useCallback(async () => {
    try {
      setError(null);
      setTranscribedText('');
      audioChunksRef.current = [];

      // Check if MediaRecorder is supported
      if (!isMediaRecorderSupported) {
        setError('Audio recording is not supported in this browser. Please use Chrome, Edge, or Safari.');
        return;
      }

      // Request microphone permission
      const stream = await navigator.mediaDevices.getUserMedia({ 
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        }
      });
      streamRef.current = stream;

      // Create MediaRecorder
      const mediaRecorder = new MediaRecorder(stream, {
        mimeType: 'audio/webm;codecs=opus'
      });

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorderRef.current = mediaRecorder;
      mediaRecorder.start(100); // Collect data every 100ms

      setIsRecording(true);
      setRecordingTime(0);

      // Start timer
      timerRef.current = setInterval(() => {
        setRecordingTime(prev => prev + 1);
      }, 1000);

    } catch (err) {
      console.error('Failed to start recording:', err);
      
      if (err.name === 'NotAllowedError') {
        setError('Microphone permission denied. Please allow microphone access.');
      } else if (err.name === 'NotFoundError') {
        setError('No microphone found. Please connect a microphone.');
      } else {
        setError('Failed to access microphone. Please try again.');
      }
    }
  }, []);

  const stopRecording = useCallback(async () => {
    return new Promise(async (resolve) => {
      try {
        setIsTranscribing(true);

        // Stop MediaRecorder
        if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
          mediaRecorderRef.current.stop();
        }

        // Stop all tracks in the stream
        if (streamRef.current) {
          streamRef.current.getTracks().forEach(track => track.stop());
        }

        // Clear timer
        if (timerRef.current) {
          clearInterval(timerRef.current);
          timerRef.current = null;
        }

        setIsRecording(false);

        // Wait a bit for the last data to be collected
        setTimeout(async () => {
          try {
            // Create audio blob from recorded chunks
            const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
            
            if (audioBlob.size === 0) {
              setError('No audio recorded. Please try again.');
              setIsTranscribing(false);
              resolve('');
              return;
            }

            // Send to backend for transcription
            const result = await api.transcribeAudio(audioBlob, languageMap[language] || 'en');
            
            if (result.success && result.text) {
              setTranscribedText(result.text);
              resolve(result.text);
            } else {
              setError(result.error || 'Failed to transcribe audio');
              resolve('');
            }
          } catch (transcribeError) {
            console.error('Transcription error:', transcribeError);
            setError(transcribeError.message || 'Failed to transcribe audio');
            resolve('');
          } finally {
            setIsTranscribing(false);
          }
        }, 500);

      } catch (err) {
        console.error('Stop recording error:', err);
        setError('Failed to stop recording');
        setIsRecording(false);
        setIsTranscribing(false);
        resolve('');
      }
    });
  }, [language]);

  const clearRecording = useCallback(() => {
    setTranscribedText('');
    setRecordingTime(0);
    setError(null);
    audioChunksRef.current = [];
  }, []);

  const getTranscript = useCallback(() => {
    return transcribedText.trim();
  }, [transcribedText]);

  const formatTime = useCallback((seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }, []);

  // Check browser support on mount
  useCallback(() => {
    if (!isMediaRecorderSupported) {
      setError('Audio recording is not supported in this browser. Please use Chrome, Edge, or Safari.');
    }
  }, []);

  // Cleanup on unmount
  useCallback(() => {
    return () => {
      if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
        mediaRecorderRef.current.stop();
      }
      if (streamRef.current) {
        streamRef.current.getTracks().forEach(track => track.stop());
      }
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, []);

  return {
    isRecording,
    isTranscribing,
    transcribedText: transcribedText.trim(),
    interimTranscript: '',
    recordingTime,
    recordingTimeFormatted: formatTime(recordingTime),
    error,
    isSupported: isMediaRecorderSupported,
    startRecording,
    stopRecording,
    clearRecording,
    getTranscript,
  };
};

export default useVoice;
