import { useState, useRef, useCallback, useEffect } from 'react';

// Map internal language codes to Web Speech API language codes
const languageMap = {
  en: 'en-US',
  bn: 'bn-BD',
  hi: 'hi-IN',
  es: 'es-ES',
  fr: 'fr-FR',
  ar: 'ar-SA',
  de: 'de-DE',
  zh: 'zh-CN',
  ja: 'ja-JP',
};

// Check if Web Speech API is available
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const isSpeechRecognitionSupported = !!SpeechRecognition;

export const useVoice = (language = 'en') => {
  const [isRecording, setIsRecording] = useState(false);
  const [transcribedText, setTranscribedText] = useState('');
  const [recordingTime, setRecordingTime] = useState(0);
  const [error, setError] = useState(null);
  const [speechLanguage, setSpeechLanguage] = useState(languageMap[language] || 'en-US');
  const [interimTranscript, setInterimTranscript] = useState('');
  
  const recognitionRef = useRef(null);
  const streamRef = useRef(null);
  const timerRef = useRef(null);

  // Update speech language when language prop changes
  useEffect(() => {
    setSpeechLanguage(languageMap[language] || 'en-US');
  }, [language]);

  // Initialize speech recognition
  const initializeRecognition = useCallback(() => {
    if (!isSpeechRecognitionSupported) {
      setError('Speech recognition is not supported in this browser. Please use Chrome, Edge, or Safari.');
      return null;
    }

    const recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = speechLanguage;
    recognition.maxAlternatives = 1;

    recognition.onstart = () => {
      console.log('Speech recognition started');
      setIsRecording(true);
      setError(null);
    };

    recognition.onresult = (event) => {
      let interimTranscript = '';
      let finalTranscript = '';

      for (let i = event.resultIndex; i < event.results.length; i++) {
        const transcript = event.results[i][0].transcript;
        if (event.results[i].isFinal) {
          finalTranscript += transcript;
        } else {
          interimTranscript += transcript;
        }
      }

      if (finalTranscript) {
        setTranscribedText(prev => prev + ' ' + finalTranscript);
      }
      setInterimTranscript(interimTranscript);
    };

    recognition.onerror = (event) => {
      console.error('Speech recognition error:', event.error);
      
      let errorMessage = 'Speech recognition error';
      switch (event.error) {
        case 'no-speech':
          errorMessage = 'No speech detected. Please try again.';
          break;
        case 'audio-capture':
          errorMessage = 'Microphone not found. Please connect a microphone.';
          break;
        case 'not-allowed':
          errorMessage = 'Microphone permission denied. Please allow microphone access.';
          break;
        case 'network':
          errorMessage = 'Network error occurred.';
          break;
        default:
          errorMessage = `Error: ${event.error}`;
      }
      
      setError(errorMessage);
      setIsRecording(false);
    };

    recognition.onend = () => {
      console.log('Speech recognition ended');
      setIsRecording(false);
      setInterimTranscript('');
    };

    return recognition;
  }, [speechLanguage]);

  const startRecording = useCallback(async () => {
    try {
      setError(null);
      setTranscribedText('');

      // Request microphone permission first
      const stream = await navigator.mediaDevices.getUserMedia({ 
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        }
      });
      streamRef.current = stream;
      
      // Stop the stream tracks after getting permission (we don't need to keep recording audio)
      stream.getTracks().forEach(track => track.stop());

      // Initialize and start speech recognition
      const recognition = initializeRecognition();
      if (!recognition) {
        return;
      }

      recognitionRef.current = recognition;
      recognition.start();

      // Start timer
      setRecordingTime(0);
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
  }, [initializeRecognition]);

  const stopRecording = useCallback(() => {
    // Stop speech recognition
    if (recognitionRef.current) {
      recognitionRef.current.stop();
      recognitionRef.current = null;
    }

    // Clear timer
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }

    setIsRecording(false);
  }, []);

  const clearRecording = useCallback(() => {
    setTranscribedText('');
    setInterimTranscript('');
    setRecordingTime(0);
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
  useEffect(() => {
    if (!isSpeechRecognitionSupported) {
      setError('Speech recognition is not supported in this browser. Please use Chrome, Edge, or Safari.');
    }
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (recognitionRef.current) {
        recognitionRef.current.stop();
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
    transcribedText: transcribedText.trim(),
    interimTranscript,
    recordingTime,
    recordingTimeFormatted: formatTime(recordingTime),
    error,
    isSupported: isSpeechRecognitionSupported,
    startRecording,
    stopRecording,
    clearRecording,
    getTranscript,
  };
};

export default useVoice;
