# Quick Setup Guide - Enhanced Proctored Interview System

## 🚀 Installation Steps

### Step 1: Update Database Schema
Run this in your MySQL client or phpMyAdmin:

```sql
-- Add new columns to interview_results table
ALTER TABLE interview_results ADD COLUMN communication_score TINYINT UNSIGNED DEFAULT 5 AFTER ai_score;
ALTER TABLE interview_results ADD COLUMN confidence_score TINYINT UNSIGNED DEFAULT 5 AFTER communication_score;
ALTER TABLE interview_results ADD COLUMN discipline_score TINYINT UNSIGNED DEFAULT 100 AFTER ai_suggestions;
ALTER TABLE interview_results ADD COLUMN head_violations INT DEFAULT 0 AFTER discipline_score;
ALTER TABLE interview_results ADD COLUMN tab_switches INT DEFAULT 0 AFTER head_violations;
ALTER TABLE interview_results ADD COLUMN interview_type ENUM('standard','proctored') DEFAULT 'standard' AFTER tab_switches;
```

### Step 2: File Replacement
Copy the new enhanced interview page:
- **Old**: `frontend/pages/interview.html` (original, still works)
- **New**: `frontend/pages/interview-enhanced.html` (new proctored version)

To use the new version, update navigation links to point to `interview-enhanced.html`

### Step 3: Backend API Updates
The file `backend/api/interview.php` has been updated with:
- `callGeminiEvaluateEnhanced()` - New function for comprehensive scoring
- `evaluateAnswer()` - Updated to handle discipline metrics
- `getFallbackEnhanced()` - Improved fallback scoring

**No manual changes needed** - the updates are already applied.

### Step 4: Verify Configuration
Ensure `backend/includes/helpers.php` has:
- Valid Google Gemini API key
- Correct database connection

---

## 📋 Features Checklist

- [x] Real-time camera access with Face-API detection
- [x] Head rotation tracking (yaw, pitch angles)
- [x] Warning-based discipline system (3 levels)
- [x] Tab switch detection
- [x] Face loss detection (3-5 seconds)
- [x] Multiple face detection
- [x] Comprehensive scoring model (5 metrics)
- [x] Event logging for debugging
- [x] Visual discipline score widget
- [x] Detailed post-interview report
- [x] Per-question breakdown with tips
- [x] Communication + Confidence subscores
- [x] Camera discipline scoring
- [x] Completion consistency tracking

---

## 🎯 Test Scenarios

### Scenario 1: Clean Interview
```
Steps:
1. Start interview
2. Keep camera on, head straight
3. Answer all 5 questions clearly
4. No tab switches

Expected Result:
- Discipline Score: 100
- Warnings: 0
- Overall Score: 70-85% (depends on answer quality)
- Status: ✓ All green
```

### Scenario 2: One Warning
```
Steps:
1. Start interview
2. Turn head >30° once
3. Answer questions
4. No tab switches

Expected Result:
- Discipline Score: 95-100
- Warnings: 1
- Visual: ⚠️ Yellow warning indicator
- Toast: "Looking away from camera"
```

### Scenario 3: Multiple Violations
```
Steps:
1. Start interview
2. Turn head multiple times (>5)
3. Switch tabs once
4. Answer 3+ questions

Expected Result:
- Discipline Score: 80-90
- Warnings: 3+
- Final Report: "Low Interview Discipline marked"
- Recommendation: "Maintain eye contact and reduce distractions"
```

---

## 📊 Scoring Examples

### Example 1: Strong Interview
```
Answer Quality:        78/100 (40% weight) = 31.2 pts
Communication:         75/100 (25% weight) = 18.75 pts
Confidence & Pace:     80/100 (15% weight) = 12 pts
Camera Discipline:     95/100 (10% weight) = 9.5 pts
Completion:            90/100 (10% weight) = 9 pts
───────────────────────────────────────────
Overall Mock Score:                75.95%
Discipline:                        95/100
```

### Example 2: Needs Improvement
```
Answer Quality:        55/100 (40% weight) = 22 pts
Communication:         60/100 (25% weight) = 15 pts
Confidence & Pace:     50/100 (15% weight) = 7.5 pts
Camera Discipline:     70/100 (10% weight) = 7 pts
Completion:            65/100 (10% weight) = 6.5 pts
───────────────────────────────────────────
Overall Mock Score:                58%
Discipline:                        75/100
Warnings:                          2/3
Recommendation:        Focus on STAR method and maintain eye contact
```

---

## 🔧 Browser Compatibility

| Browser | Camera | Face-API | Speech Recognition | Status |
|---------|--------|----------|-------------------|--------|
| Chrome 90+ | ✓ | ✓ | ✓ | **Recommended** |
| Edge 90+ | ✓ | ✓ | ✓ | **Recommended** |
| Firefox 89+ | ✓ | ✓ | ✓ | Works |
| Safari 14+ | ⚠️ | ⚠️ | Limited | Limited support |

**Important**: HTTPS required for camera access (not needed for localhost)

---

## 🐛 Troubleshooting

### Issue: "Camera access denied"
**Solution**: 
- Check browser camera permissions: `chrome://settings/content/camera`
- Grant permission to localhost/your domain
- Reload the page

### Issue: "Face detection not working"
**Solution**:
- Wait 2-3 seconds for Face-API models to load
- Open browser DevTools (F12) → Console
- Check for TensorFlow.js CDN errors
- Try reloading the page

### Issue: "Warnings not triggering"
**Solution**:
- Ensure camera is enabled (green status)
- Check Console for JavaScript errors
- Verify face is clearly visible
- Head must rotate >25° for detection

### Issue: "Gemini API returning errors"
**Solution**:
- Verify API key in `backend/includes/helpers.php`
- Check Google Cloud API quota
- System uses fallback scoring if API fails
- Check error log in `error_log`

---

## 📱 API Endpoints

### GET `/backend/api/interview.php?action=status`
Returns interview attempt status and premium status

### GET `/backend/api/interview.php?action=questions&role=Developer&job_id=123`
Returns array of 5 interview questions (custom or default)

### POST `/backend/api/interview.php?action=evaluate`
**Body:**
```json
{
  "question": "Tell me about yourself...",
  "answer": "I am a developer with...",
  "job_id": 123,
  "head_violations": 2,
  "tab_switches": 1,
  "warnings": 1,
  "interview_type": "proctored"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "rating": "Good",
    "score": 7,
    "communication_score": 7,
    "confidence_score": 6,
    "feedback": "Good structure...",
    "suggestions": "Add specific metrics..."
  }
}
```

### GET `/backend/api/interview.php?action=results`
Returns previous interview sessions for the user

---

## 🎓 Student Information Page Updates

Consider adding to your dashboard/help page:

```html
<div class="info-box">
  <h3>📹 Interview Discipline Tracking</h3>
  <p><strong>Warning 1:</strong> Notification only (no penalty)</p>
  <p><strong>Warning 2:</strong> 5 pts deducted from discipline score</p>
  <p><strong>Warning 3+:</strong> Marked in final report as "Low Discipline"</p>
  
  <h4>Tracked Issues:</h4>
  <ul>
    <li>Looking away from camera (head rotation >25°)</li>
    <li>Face not visible for 5+ seconds</li>
    <li>Multiple faces in frame</li>
    <li>Switching tabs/windows</li>
    <li>Microphone not detected</li>
  </ul>
  
  <p><strong>Final Report:</strong> Comprehensive scoring with 5 metrics (Answer Quality, Communication, Confidence, Camera Discipline, Completion)</p>
</div>
```

---

## 📞 Support

For issues or questions:
1. Check browser console (F12) for errors
2. Verify camera/microphone permissions
3. Test with Chrome/Edge (most compatible)
4. Ensure HTTPS for production

---

## Version Info
- **System Version**: 2.0 (Enhanced Proctored)
- **Release Date**: May 2026
- **Last Updated**: May 13, 2026
- **Compatibility**: All modern browsers with camera support

---

**Ready to launch!** 🚀 Users can now experience realistic, proctored interview simulations with comprehensive feedback.
