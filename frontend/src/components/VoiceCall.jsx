import React, { useState, useEffect, useRef, useCallback } from 'react';
import webrtcService from '../services/webrtc';
import './VoiceCall.css';

const VoiceCall = ({ 
  calleeId, 
  calleeName = 'Unknown',
  onCallStarted,
  onCallEnded,
  incomingCall = false,
  incomingCallData = null,
}) => {
  const [callStatus, setCallStatus] = useState('idle'); // idle, calling, ringing, connected, ended
  const [isMuted, setIsMuted] = useState(false);
  const [callDuration, setCallDuration] = useState(0);
  const [error, setError] = useState(null);
  const [sessionId, setSessionId] = useState(null);
  
  const localAudioRef = useRef(null);
  const remoteAudioRef = useRef(null);
  const timerRef = useRef(null);

  // Initialize WebRTC service
  useEffect(() => {
    const initializeWebRTC = async () => {
      try {
        await webrtcService.initialize();
        setupEventListeners();
      } catch (err) {
        console.error('Failed to initialize WebRTC:', err);
        setError('Failed to initialize voice call service');
      }
    };

    initializeWebRTC();

    return () => {
      webrtcService.cleanup();
    };
  }, []);

  // Handle incoming calls
  useEffect(() => {
    if (incomingCall && incomingCallData) {
      handleIncomingCall(incomingCallData);
    }
  }, [incomingCall, incomingCallData]);

  // Update audio elements when streams change
  useEffect(() => {
    const localStream = webrtcService.getLocalStream();
    if (localStream && localAudioRef.current) {
      localAudioRef.current.srcObject = localStream;
    }

    const remoteStream = webrtcService.getRemoteStream();
    if (remoteStream && remoteAudioRef.current) {
      remoteAudioRef.current.srcObject = remoteStream;
    }
  }, [callStatus]);

  // Call duration timer
  useEffect(() => {
    if (callStatus === 'connected') {
      timerRef.current = setInterval(() => {
        setCallDuration(prev => prev + 1);
      }, 1000);
    } else {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    }

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, [callStatus]);

  const setupEventListeners = () => {
    webrtcService.on('onCallInitiated', (data) => {
      setSessionId(data.sessionId);
      setCallStatus('calling');
      if (onCallStarted) onCallStarted(data);
    });

    webrtcService.on('onCallRinging', () => {
      setCallStatus('ringing');
    });

    webrtcService.on('onCallConnected', () => {
      setCallStatus('connected');
    });

    webrtcService.on('onCallEnded', (data) => {
      setCallStatus('ended');
      setCallDuration(data?.duration || 0);
      if (onCallEnded) onCallEnded(data);
    });

    webrtcService.on('onRemoteStream', (stream) => {
      if (remoteAudioRef.current) {
        remoteAudioRef.current.srcObject = stream;
      }
    });

    webrtcService.on('onError', (errorMessage) => {
      setError(errorMessage);
      setCallStatus('idle');
    });

    webrtcService.on('onOffer', async (data) => {
      try {
        await webrtcService.handleOffer(data);
        setSessionId(data.session_id);
        setCallStatus('ringing');
      } catch (err) {
        console.error('Failed to handle offer:', err);
        setError('Failed to accept incoming call');
      }
    });

    webrtcService.on('onAnswer', async (data) => {
      try {
        await webrtcService.handleAnswer(data);
        setCallStatus('connected');
      } catch (err) {
        console.error('Failed to handle answer:', err);
      }
    });

    webrtcService.on('onIceCandidate', async (data) => {
      try {
        await webrtcService.handleIceCandidate(data);
      } catch (err) {
        console.error('Failed to handle ICE candidate:', err);
      }
    });
  };

  const handleIncomingCall = async (callData) => {
    try {
      setSessionId(callData.session_id);
      setCallStatus('ringing');
      // Auto-accept incoming calls for now
      await webrtcService.handleOffer(callData);
    } catch (err) {
      console.error('Failed to handle incoming call:', err);
      setError('Failed to accept incoming call');
    }
  };

  const initiateCall = async () => {
    try {
      setError(null);
      setCallDuration(0);
      await webrtcService.initiateCall(calleeId, 'audio');
    } catch (err) {
      console.error('Failed to initiate call:', err);
      setError('Failed to start call. Please check microphone permissions.');
    }
  };

  const endCall = async () => {
    try {
      await webrtcService.endCall();
    } catch (err) {
      console.error('Failed to end call:', err);
    }
  };

  const toggleMute = () => {
    const newMuteState = !isMuted;
    webrtcService.toggleMute(newMuteState);
    setIsMuted(newMuteState);
  };

  const formatDuration = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  const getStatusText = () => {
    switch (callStatus) {
      case 'calling':
        return `Calling ${calleeName}...`;
      case 'ringing':
        return `${calleeName} is ringing...`;
      case 'connected':
        return `Connected - ${formatDuration(callDuration)}`;
      case 'ended':
        return `Call ended - ${formatDuration(callDuration)}`;
      default:
        return 'Ready to call';
    }
  };

  // Render call button
  if (callStatus === 'idle') {
    return (
      <div className="voice-call-container">
        <div className="voice-call-card">
          <div className="voice-call-header">
            <h3>Voice Call</h3>
          </div>
          <div className="voice-call-content">
            <div className="callee-info">
              <div className="callee-avatar">
                {calleeName.charAt(0).toUpperCase()}
              </div>
              <p className="callee-name">{calleeName}</p>
            </div>
            <button className="call-button initiate" onClick={initiateCall}>
              <span className="call-icon">📞</span>
              Start Voice Call
            </button>
            {error && <p className="error-message">{error}</p>}
          </div>
        </div>
        
        {/* Hidden audio elements for WebRTC */}
        <audio ref={localAudioRef} autoPlay muted />
        <audio ref={remoteAudioRef} autoPlay />
      </div>
    );
  }

  // Render active call
  return (
    <div className="voice-call-container">
      <div className={`voice-call-card active-call ${callStatus}`}>
        <div className="voice-call-header">
          <h3>Voice Call</h3>
          <span className={`call-status-indicator ${callStatus}`}>
            {callStatus === 'connected' ? '🟢' : callStatus === 'ringing' ? '🟡' : '🔴'}
          </span>
        </div>
        
        <div className="voice-call-content">
          <div className="callee-info">
            <div className="callee-avatar large">
              {calleeName.charAt(0).toUpperCase()}
            </div>
            <p className="callee-name">{calleeName}</p>
            <p className="call-status-text">{getStatusText()}</p>
          </div>

          {callStatus === 'connected' && (
            <div className="call-controls">
              <button 
                className={`control-button ${isMuted ? 'active' : ''}`}
                onClick={toggleMute}
                title={isMuted ? 'Unmute' : 'Mute'}
              >
                {isMuted ? '🔇' : '🎤'}
              </button>
              <button 
                className="control-button end-call"
                onClick={endCall}
                title="End call"
              >
                📞
              </button>
            </div>
          )}

          {(callStatus === 'calling' || callStatus === 'ringing') && (
            <button className="call-button cancel" onClick={endCall}>
              Cancel
            </button>
          )}

          {callStatus === 'ended' && (
            <button className="call-button" onClick={() => setCallStatus('idle')}>
              New Call
            </button>
          )}

          {error && <p className="error-message">{error}</p>}
        </div>
      </div>

      {/* Hidden audio elements for WebRTC */}
      <audio ref={localAudioRef} autoPlay muted />
      <audio ref={remoteAudioRef} autoPlay />
    </div>
  );
};

export default VoiceCall;
