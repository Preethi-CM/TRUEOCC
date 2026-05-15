# AI Proctored Interview System - Enhanced Documentation

## Overview
The enhanced interview system combines real-time proctoring, AI-powered evaluation, and comprehensive discipline tracking to provide realistic interview simulations with detailed performance feedback.

---

## Key Features

### 1. **Real-Time Proctoring with TensorFlow.js**
- **Live Camera Access**: Captures video stream in real-time
- **Face Detection**: Uses Face-API for face landmark detection
- **Head Rotation Tracking**: Monitors head position and angle
- **Multiple Face Detection**: Alerts if multiple people in frame
- **Tab Switch Detection**: Tracks user attention during interview

### 2. **Discipline-Based Warning System** (NEW)

#### Warning Levels:
1. **Warning 1**: Soft message, NO penalty
   - Example: "Looking away from camera"
   - User Impact: Toast notification only
   - Score Impact: 0 pts

2. **Warning 2**: Yellow status, SMALL discipline deduction (5 pts)
   - Triggered after first warning on same issue
   - Score Impact: 5 pts deducted from discipline
   - Visual: Yellow status indicator

3. **Warning 3+**: Marked "Low Interview Discipline"
   - Triggered after multiple violations
   - Score Impact: 3 pts per additional warning
   - Final Report: Shows "Low Interview Discipline" mark
   - Benefits from cumulative tracking

#### Tracked Events:
- **Face Detection Loss** (3-5 seconds): "Face not detected for 5+ seconds"
- **Head Rotation**: "Looking away from camera" (auto-triggers if >30° yaw, >20° pitch)
- **Multiple Faces**: "Multiple faces detected in frame"
- **Tab Switches**: "Tab switched during interview" (cumulative)
- **Microphone Issues**: Tracked when audio stops
- **Silence Periods**: Monitored via audio input

---

## Comprehensive Scoring Model

### Score Breakdown (Weighted):

| Metric | Weight | Scale | Description |
|--------|--------|-------|-------------|
| **Answer Quality** | 40% | 0-100 | Content, structure, specificity, examples |
| **Communication Clarity** | 25% | 0-100 | Articulation, flow, logical progression |
| **Confidence & Pace** | 15% | 0-100 | Delivery, speaking pace, no long pauses |
| **Camera Discipline** | 10% | 0-100 | Eye contact, head position, focus |
| **Completion & Consistency** | 10% | 0-100 | All questions attempted, answer depth |

### Final Report Section Breakdown:

```
Overall Mock Score: 73%
├─ Answer Quality: 78/100 (40% weight)
├─ Communication: 70/100 (25% weight)
├─ Confidence & Pace: 65/100 (15% weight)
├─ Camera Discipline: 62/100 (10% weight)
└─ Completion: 85/100 (10% weight)

Discipline Score: 85/100
Warnings Issued: 2/3

Overall = (78×0.40) + (70×0.25) + (65×0.15) + (62×0.10) + (85×0.10) = 73%
```

---

## Enhanced API Response

### New `evaluate` Action Parameters:
```json
{
  "question": "Tell me about yourself...",
  "answer": "I am a software developer with...",
  "job_id": 123,
  "head_violations": 4,
  "tab_switches": 1,
  "warnings": 2,
  "interview_type": "proctored"
}
```

### New Response Fields:
```json
{
  "success": true,
  "data": {
    "rating": "Good",
    "score": 7,
    "communication_score": 7,
    "confidence_score": 6,
    "feedback": "Relevant answer with good structure...",
    "suggestions": "Add specific metrics...",
    "communication_tips": "Use transitions for clarity...",
    "confidence_tips": "Maintain steady pace..."
  }
}
```

---

## Database Schema Updates

### New `interview_results` Columns:
```sql
ALTER TABLE interview_results ADD COLUMN communication_score TINYINT UNSIGNED DEFAULT 5;
ALTER TABLE interview_results ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 5;
ALTER TABLE interview_results ADD COLUMN discipline_score TINYINT UNSIGNED DEFAULT 100;
ALTER TABLE interview_results ADD COLUMN head_violations INT DEFAULT 0;
ALTER TABLE interview_results ADD COLUMN tab_switches INT DEFAULT 0;
ALTER TABLE interview_results ADD COLUMN interview_type ENUM('standard','proctored') DEFAULT 'standard';
```

---

## Front-End Implementation

### New JavaScript Variables:
```javascript
let disciplineScore = 100;           // Starts at 100, deducted for violations
let warnings = 0;                    // Count of warnings issued
let headTurnWarnings = 0;            // Specific head movement tracking
let tabSwitchCount = 0;              // Tab switch counter
let disciplineEvents = [];           // Event log for debugging
let interviewMetrics = {             // Final scoring breakdown
  answerQuality: 0,
  communicationClarity: 0,
  confidencePace: 0,
  cameraDisc: 100,
  completionConsistency: 100
};
```

### Key Functions:

#### `issueWarning(message, type)`
Handles warning escalation:
```javascript
// Warning 1: Soft message only
// Warning 2: Deduct 5 pts + toast
// Warning 3+: Deduct 3 pts + mark discipline issue
```

#### `updateProctorUI()`
Displays real-time status with color-coding:
- ✓ Green: OK
- ⚠️ Yellow: Warning
- ✕ Red: Alert/Low

#### `renderComprehensiveReport(feedbacks)`
Generates final report with:
- Overall weighted score
- Per-metric breakdown with visual bars
- Discipline warnings summary
- Per-question detailed feedback
- Actionable next steps

---

## User Experience Flow

1. **Pre-Interview Gate**
   - Display warning explanation
   - Show tracked events
   - Option to start proctored interview

2. **During Interview**
   - Real-time camera + proctoring panel side-by-side
   - Discipline score widget (0-100)
   - Live warning banner if violations occur
   - Per-question AI HR tips

3. **Post-Interview Report**
   - Overall score ring (0-100%)
   - Detailed metrics breakdown
   - Discipline score with warnings breakdown
   - Event log (optional)
   - Per-question feedback with tips
   - Next steps recommendations

---

## Example Warning Flow

```
Interview Start → Discipline Score: 100

00:45 - Head turns 35° → First warning (soft)
        Toast: "⚠️ Looking away from camera"
        Discipline: Still 100 (no penalty)

02:15 - Face not visible for 6s → Second warning (deduction)
        Toast: "⚠️ Warning 2/3: Face not detected for 5+ seconds (5 pts deducted)"
        Discipline: 95

03:30 - Tab switched → Third warning+ (deduction)
        Toast: "⚠️ Tab switched during interview (3 pts deducted)"
        Discipline: 92
        Event: "Low interview discipline marked"

Report: Discipline Score 92, Warnings: 3
Final Report shows: "Low Interview Discipline detected"
Recommendation: "Maintain eye contact and reduce distractions"
```

---

## Technical Stack

- **Face Detection**: Face-API + TensorFlow.js
- **Speech Recognition**: Web Speech API
- **Text-to-Speech**: Web Audio API
- **Video Capture**: MediaDevices API
- **Backend Evaluation**: Google Gemini API
- **Database**: MySQL (updated schema)

---

## Migration Steps

1. **Update database schema** (run provided ALTER statements)
2. **Replace interview.html** with `interview-enhanced.html`
3. **Update backend API** (interview.php changes)
4. **Test camera/microphone access** in Chrome/Edge
5. **Verify Gemini API** key is configured in helpers.php

---

## Performance Metrics

- Face detection runs every 300ms
- Warning debouncing: prevents spam (3-5s between same warnings)
- Event log limited to 30 entries (auto-trim)
- Overall computation: ~50-100ms per frame

---

## Future Enhancements

- Screen recording capability
- Posture detection (body language)
- Background blur detection
- Sound level monitoring
- Emotion/sentiment analysis
- AI HR chatbot with follow-up questions
- Plagiarism detection via Turnitin API
- Integration with real job requirements

---

## Support & Troubleshooting

### Camera Not Detected
- Check browser permissions: chrome://settings/content/camera
- Ensure HTTPS (required for camera access)
- Try different browser (Firefox, Chrome recommended)

### Face Detection Not Working
- Clear browser cache
- Reload page (Face-API models need to load)
- Check network for TensorFlow.js CDN access

### Gemini API Errors
- Verify API key in `backend/includes/helpers.php`
- Check rate limits on Google Cloud Console
- Fallback scoring activated if API fails

---

## Version History

- **v2.0** (Current): Enhanced proctoring + comprehensive scoring + discipline tracking
- **v1.0**: Basic interview with speech recognition + Gemini evaluation

---

Generated: May 2026
Last Updated: May 13, 2026
