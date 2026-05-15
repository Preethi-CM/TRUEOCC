# TrueOcc Premium Features — API & Feature Integration Guide

---

## 📡 SYSTEM ARCHITECTURE DIAGRAM

```
┌─────────────────────────────────────────────────────────────────────┐
│                          FRONTEND (React/Vanilla JS)                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Dashboard        Interview Studio    Skill Gaps     Roadmap       │
│     Page            Enhanced             Page         Page         │
│      │                   │                  │           │           │
│      └───────────────────┼──────────────────┼───────────┘           │
│                          │                  │                       │
│                      API Client (main.js)                           │
│                          │                                          │
└──────────────────────────┼──────────────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼─────────┐ ┌────────────────┐ ┌───────────────┐
│ /api/user.php   │ │ /api/inter...  │ │ /api/resources│
│                 │ │   view.php     │ │    .php       │
├─────────────────┤ ├────────────────┤ ├───────────────┤
│• getReadiness   │ │• recordAnswer  │ │• getRecommend │
│• updateReadines │ │• submitInterview│ │• searchBySkill│
│• getSkillGaps   │ │• evaluateAnswer │ │• rateResource │
│• getRoadmap     │ ├────────────────┤ └───────────────┘
│• generateRoadmap│ │ Enhanced AI     │
│• completTaskmap │ │ Feedback       │
└─────────────────┘ └────────────────┘
        │                   │
        └───────────────────┴──────────────────┐
                                               │
┌──────────────────────────────────────────────▼───────────────────┐
│              BACKEND (PHP Classes)                                │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────┐  ┌─────────────────┐  ┌────────────────┐   │
│  │ScoreCalculator   │  │SkillGapAnalyzer │  │RoadmapGenerator│   │
│  ├──────────────────┤  ├─────────────────┤  ├────────────────┤   │
│  │• calculateScore  │  │• analyzeGaps    │  │• generateRoadmap   │
│  │• getResumeScore  │  │• classifyGaps   │  │• selectTemplate    │
│  │• getProfileScore │  │• prioritizeSkill│  │• createTasks   │   │
│  │• getAptitudeScore│  │• getResources   │  │• initTracking  │   │
│  │• getInterviewScor│  │• calcPriority   │  └────────────────┘   │
│  │• getSkillMatch   │  └─────────────────┘                       │
│  │• getConsistency  │                                            │
│  │• saveScore       │                                            │
│  └──────────────────┘                                            │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐    │
│  │              External Services                           │    │
│  ├──────────────────────────────────────────────────────────┤    │
│  │• Gemini AI (for interview feedback)                      │    │
│  │• Google Cloud Storage (for video recordings)             │    │
│  │• Email Service (for notifications)                       │    │
│  │• TensorFlow.js (client-side video analysis)              │    │
│  └──────────────────────────────────────────────────────────┘    │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
┌───────▼─────────┐  ┌─────────▼──────────┐  ┌──────▼────────┐
│  MYSQL Database │  │  Redis Cache       │  │  File Storage │
├─────────────────┤  ├────────────────────┤  ├───────────────┤
│ • users         │  │• Score cache       │  │ • Videos      │
│ • resumes       │  │• Skill gaps cache  │  │ • Documents   │
│ • scores        │  │• Resources cache   │  │ • Images      │
│ • skill_gaps    │  │• Roadmap cache     │  └───────────────┘
│ • interviews    │  └────────────────────┘
│ • feedback      │
│ • roadmaps      │
│ • resources     │
└─────────────────┘
```

---

## 🔄 FEATURE INTERACTION FLOWS

### Flow 1: User Signs Up → Gets Initial Score

```
┌─ User Signs Up
│
├─ Profile Created (users table)
│
├─ [Trigger] Initialize Readiness Score
│  └─ ScoreCalculator.calculateScore(user_id)
│     ├─ Get resume data (if exists)
│     ├─ Get profile data (if exists)
│     ├─ Get aptitude scores (if exists)
│     ├─ Get interview attempts (if exists)
│     ├─ Calculate composite score
│     └─ Save to user_readiness_scores
│
├─ [Trigger] Generate Default Roadmap (if score < 70)
│  └─ RoadmapGenerator.generateRoadmap(user_id, score)
│     ├─ Select template based on score
│     ├─ Create roadmap instance
│     ├─ Initialize week tasks
│     └─ Set progress tracking
│
├─ [Dashboard] Show on Next Login
│  ├─ Score Card displays: "Welcome! You're 30/100 Early Stage"
│  ├─ Roadmap Card shows: "Week 1: Resume Polish"
│  ├─ Insights Card: "Complete your profile first"
│  └─ CTA: "Start Your 6-Week Plan"
│
└─ User now has a clear path forward
```

### Flow 2: User Takes Interview → Gets AI Feedback → Score Updates

```
┌─ User Starts Interview Practice
│
├─ [Permission Flow]
│  ├─ Request camera access
│  ├─ Request microphone access
│  ├─ Show live preview
│  ├─ Show mic level indicator
│  └─ User confirms ready
│
├─ [Question Flow]
│  ├─ Show Q1, start timer (3 min)
│  ├─ Record video + audio
│  ├─ User answers
│  ├─ User clicks "Continue"
│  ├─ Send to backend:
│  │  {
│  │    question_id: 1,
│  │    answer_text: "I am...",
│  │    duration: 180,
│  │    video_url: "s3://...",
│  │    attempt_num: 1
│  │  }
│  └─ Repeat for all 5 questions
│
├─ [AI Evaluation]
│  ├─ Backend receives answer
│  ├─ Store in interview_attempts table
│  ├─ Call Gemini API for feedback
│  │  {
│  │    "answer_quality": 75,
│  │    "communication": 70,
│  │    "confidence": 68,
│  │    "feedback": "Good structure but add examples"
│  │  }
│  ├─ Store in interview_feedback table
│  └─ Calculate overall score
│
├─ [Feedback Display]
│  ├─ Show feedback cards:
│  │  - Answer Quality: 75/100
│  │  - Communication: 70/100
│  │  - Confidence: 68/100
│  │  - Discipline: 85/100
│  │  - Consistency: 80/100
│  ├─ Overall: 76/100 (Good!)
│  ├─ Show strengths
│  ├─ Show areas to improve
│  └─ Suggest linked learning
│
├─ [Score Update - Weekly]
│  ├─ Cron job runs Monday 2 AM
│  ├─ Recalculate interview_score
│  │  = Avg of best 3 interview attempts
│  ├─ Recalculate overall readiness_score
│  ├─ Check if improved
│  ├─ Update user_readiness_scores
│  ├─ Store in history
│  └─ Send celebration email if improved
│
├─ [Dashboard Update]
│  ├─ User logs in and sees new score
│  ├─ Sees trend: "↑ +3 pts this week"
│  ├─ Interview section updates
│  ├─ Roadmap may auto-advance
│  └─ Suggestions updated
│
└─ Cycle continues: Practice → Feedback → Score Update → Motivation
```

### Flow 3: Dashboard Shows Skill Gaps → User Starts Learning

```
┌─ Dashboard loads
│
├─ [Dashboard Initialization]
│  ├─ Load user readiness score
│  ├─ Load user roadmap
│  └─ Load skill gaps (from cache/DB)
│
├─ [Skill Gaps Section]
│  ├─ Display "Skill Gap Analysis" card
│  ├─ Show top 3 missing skills
│  │  1. React (95% of jobs need this)
│  │  2. System Design (80% need this)
│  │  3. AWS (60% need this)
│  ├─ Each skill shows:
│  │  - Skill name
│  │  - Jobs that need it (%)
│  │  - Current level: None
│  │  - Needed level: Advanced
│  │  - Time to learn: 4-6 weeks
│  └─ CTA: "Start Learning React"
│
├─ [User Clicks "Start Learning React"]
│  ├─ Navigate to skill-gaps page
│  ├─ Show full React gap:
│  │  - Description: "Most popular frontend framework"
│  │  - Jobs: 95 out of 100 in your category
│  │  - Your level: None
│  │  - Needed: Advanced
│  │  - Learning time: 4-6 weeks
│  │  - Priority: 95/100
│  │
│  └─ Recommended resources:
│     ├─ Book: "Learning React" (4.8★)
│     ├─ Course: "Complete React" (4.9★)
│     └─ Project: "Build Todo App" (4.7★)
│
├─ [User Starts Learning]
│  ├─ Click "Start Learning Path"
│  ├─ Update user_skill_gaps.status = "In Progress"
│  ├─ Week 1-4 tasks added to roadmap
│  ├─ Progress tracking initialized
│  └─ Email sent: "You started learning React!"
│
├─ [Weekly Update]
│  ├─ Check progress on skill
│  ├─ Calculate new skill_match_score
│  ├─ Recalculate overall readiness
│  ├─ Update dashboard insights
│  └─ Show progress: "React: 0% → 15% complete"
│
└─ User is now on a guided learning path
```

### Flow 4: Weekly Roadmap Progression

```
Week 1: Resume Polish
├─ Tasks:
│  ├─ Read: "Resume that Works" (20 pages)
│  ├─ Update experience section
│  ├─ Add 3 projects to resume
│  ├─ Get resume reviewed
│  └─ Apply to 5 jobs
├─ Progress: [User completes tasks]
├─ Dashboard shows: "Week 1: 4/5 tasks done ■■■■░"
└─ Expected score gain: +10 pts

Week 2: Aptitude Fundamentals
├─ Tasks:
│  ├─ Course: Logical Reasoning (12 hrs)
│  ├─ Solve: 50 reasoning puzzles
│  ├─ Take: Full mock test (3 hrs)
│  └─ Analyze: Weak areas
├─ Progress: [User takes test]
├─ Dashboard shows: "Week 2: In Progress"
├─ Score updated: "Aptitude: 35 → 52"
└─ Expected score gain: +8 pts

Week 3: Interview Bootcamp
├─ Tasks:
│  ├─ Read: "Cracking Interviews"
│  ├─ Course: Interview skills (8 hrs)
│  ├─ Practice: 3 mock interviews
│  └─ Focus: Answer structure
├─ Progress: [User practices 3 interviews]
├─ Feedback: "Good pacing, reduce filler words"
├─ Dashboard shows: "Week 3: 2/4 tasks done"
└─ Expected score gain: +7 pts

Week 4: Technical Skills Focus
├─ Tasks:
│  ├─ Learn: React Fundamentals (20 hrs)
│  ├─ Build: Small React project
│  ├─ Practice: 20 LeetCode problems
│  └─ Project: Add to portfolio
├─ Progress: [User builds project]
├─ Dashboard shows: "Week 4: 1/4 tasks done"
└─ Expected score gain: +8 pts

Week 5: Consolidation & Practice
├─ Tasks:
│  ├─ Review: All learning
│  ├─ Test: 2 full aptitude tests
│  ├─ Interview: 2 more practice
│  └─ Apply: 10 jobs
├─ Progress: [User applies to jobs]
├─ Dashboard shows: "Week 5: 3/4 tasks done"
└─ Expected score gain: +6 pts

Week 6: Final Push & Job Search
├─ Tasks:
│  ├─ Final review
│  ├─ 2-3 more interviews
│  ├─ Update LinkedIn profile
│  └─ Apply: 15+ jobs
├─ Progress: [User finishes roadmap]
├─ Dashboard shows: "Week 6: ✓ COMPLETE!"
├─ Final score: 78 → 85 (+7 pts)
└─ Status: 🟢 Job Ready!

[After Roadmap]
├─ User sees "Ready for Interviews!" badge
├─ Get personalized job matches
├─ Apply with confidence
└─ Cycle repeats: Next roadmap or mastery goals
```

---

## 🔌 API ENDPOINT SPECIFICATION

### 1. Readiness Score API

```
GET /api/user.php?action=getReadinessScore

Response:
{
  "success": true,
  "data": {
    "overall_score": 78,
    "resume_quality_score": 72,
    "profile_completeness_score": 85,
    "aptitude_score": 60,
    "interview_score": 75,
    "skill_match_score": 82,
    "consistency_score": 95,
    "trend_bonus": 5,
    "final_score": 78,
    "status": "Job Ready",
    "last_updated": "2024-05-14 10:30:00",
    "breakdown": {
      "resume": { score: 72, label: "Resume Quality", icon: "📄" },
      "profile": { score: 85, label: "Profile", icon: "👤" },
      "aptitude": { score: 60, label: "Aptitude", icon: "🧠" },
      "interview": { score: 75, label: "Interview", icon: "💬" },
      "skills": { score: 82, label: "Skills", icon: "🎯" },
      "consistency": { score: 95, label: "Consistency", icon: "🔥" }
    }
  }
}
```

### 2. Skill Gaps API

```
GET /api/user.php?action=getSkillGaps&role=Software%20Engineer

Response:
{
  "success": true,
  "data": {
    "target_role": "Software Engineer",
    "total_skills": 25,
    "strong_skills": [
      {
        "name": "JavaScript",
        "level": "Expert",
        "frequency": 96,
        "jobs_count": 48,
        "mastery": 95
      },
      ...
    ],
    "medium_skills": [
      {
        "name": "React",
        "current_level": "Beginner",
        "required_level": "Advanced",
        "frequency": 100,
        "jobs_count": 50,
        "priority": 95,
        "estimated_hours": 80,
        "resources": [
          {
            "id": 1,
            "title": "React: The Complete Guide",
            "type": "Course",
            "rating": 4.9,
            "platform": "Udemy"
          },
          ...
        ]
      },
      ...
    ],
    "missing_skills": [
      {
        "name": "System Design",
        "current_level": "None",
        "required_level": "Advanced",
        "frequency": 80,
        "jobs_count": 40,
        "priority": 90,
        "estimated_hours": 120,
        "resources": [...]
      },
      ...
    ]
  }
}
```

### 3. Interview Submission API

```
POST /api/interview.php?action=submitInterview

Request:
{
  "session_id": "sess_abc123",
  "answers": [
    {
      "question_id": 1,
      "answer_text": "I'm a software developer...",
      "duration": 180,
      "video_base64": "data:video/webm;..."
    },
    ...
  ]
}

Response:
{
  "success": true,
  "data": {
    "attempt_id": 456,
    "scores": {
      "answer_quality": 78,
      "communication": 72,
      "confidence": 70,
      "discipline": 82,
      "consistency": 75,
      "overall": 74
    },
    "feedback": {
      "answer_quality": {
        "score": 78,
        "strengths": ["Relevant examples", "Good structure"],
        "improvements": ["Add more technical depth"],
        "action": "Review system design concepts"
      },
      "communication": {
        "score": 72,
        "strengths": ["Clear articulation", "Good pacing"],
        "improvements": ["Reduce filler words (um, like × 8)"],
        "action": "Practice speaking more slowly"
      },
      ...
    },
    "filler_words": {
      "um": 3,
      "like": 5,
      "uh": 0
    },
    "skill_gaps_detected": ["System Design", "AWS"],
    "next_actions": [
      "Start System Design learning path",
      "Practice 2 more interviews next week",
      "Reduce filler words using speech tool"
    ],
    "similar_roles": [
      { "role": "Backend Engineer", "match": 92 },
      { "role": "DevOps Engineer", "match": 85 }
    ]
  }
}
```

### 4. Roadmap Generation API

```
POST /api/user.php?action=generateRoadmap

Request:
{
  "current_score": 62,
  "target_score": 80,
  "target_role": "Software Engineer"
}

Response:
{
  "success": true,
  "data": {
    "roadmap_id": 789,
    "template": "Fast Track",
    "duration_weeks": 6,
    "expected_improvement": 18,
    "weeks": [
      {
        "week_number": 1,
        "title": "Resume Polish",
        "priority": "Critical",
        "expected_gain": 10,
        "tasks": [
          {
            "id": 1,
            "order": 1,
            "title": "Read: Resume that Works",
            "type": "Read",
            "estimated_hours": 2,
            "resource_link": "..."
          },
          ...
        ]
      },
      ...
    ],
    "expected_final_score": 80,
    "expected_final_status": "Ready"
  }
}
```

### 5. Roadmap Task Completion API

```
POST /api/user.php?action=completeRoadmapTask

Request:
{
  "roadmap_id": 789,
  "week_id": 45,
  "task_id": 156,
  "notes": "Finished reading the book, key takeaways noted"
}

Response:
{
  "success": true,
  "data": {
    "task_id": 156,
    "status": "Completed",
    "week_progress": {
      "completed": 3,
      "total": 5,
      "percent": 60
    },
    "roadmap_progress": {
      "completed_weeks": 1,
      "total_weeks": 6,
      "percent": 17
    },
    "motivational_message": "Great work! Keep it up! 🔥"
  }
}
```

### 6. Resources Recommendation API

```
GET /api/resources.php?action=getRecommended

Response:
{
  "success": true,
  "data": {
    "personalized": [
      {
        "id": 1,
        "title": "React: The Complete Guide",
        "description": "...",
        "type": "Course",
        "platform": "Udemy",
        "rating": 4.9,
        "reviews": 45000,
        "url": "https://udemy.com/...",
        "reason": "Matches your #1 skill gap",
        "rank": 1,
        "estimated_hours": 32,
        "level": "Beginner",
        "is_free": false,
        "price": "$10"
      },
      ...
    ]
  }
}
```

---

## 🔐 DATABASE RELATIONSHIPS

### Entity Relationship Diagram

```
users (1) ──── (1) user_readiness_scores
  │              └─ overall_score, trend_bonus, status
  │
  ├──── (1) resumes
  │         └─ skills, experience, education
  │
  ├──── (M) interview_attempts
  │         ├─ job_id (foreign key to jobs)
  │         ├─ recording_url
  │         └─ (1) interview_feedback
  │              ├─ overall_score
  │              ├─ feedback_json
  │              └─ filler_word_count
  │
  ├──── (M) user_skill_gaps
  │         ├─ target_role
  │         ├─ skill_name
  │         ├─ gap_type (Strong/Medium/Missing)
  │         └─ learning_path_id
  │
  ├──── (M) user_roadmaps
  │         ├─ template_id (foreign key to roadmap_templates)
  │         ├─ status (Active/Completed)
  │         └─ (M) user_roadmap_progress
  │              ├─ week_id (FK to roadmap_weeks)
  │              ├─ task_id (FK to roadmap_tasks)
  │              └─ status (Completed/In Progress)
  │
  └──── (1) readiness_score_history
         └─ overall_score (copied weekly)

readmap_templates (1) ──── (M) roadmap_weeks
                            └─ week_number
                            └─ (M) roadmap_tasks
                               ├─ task_type
                               ├─ estimated_hours
                               └─ resource_links
```

---

## 💾 DATA FLOW EXAMPLE

### Complete User Journey Data

```
User: Akash (ID: 123)

[Day 1: Signup]
├─ users: {"id": 123, "name": "Akash", ...}
├─ resumes: {"user_id": 123, "skills": "Java, JavaScript", ...}
└─ user_readiness_scores: {"user_id": 123, "overall_score": 30, "status": "Early"}

[Day 2: Completes Profile]
├─ users: updated with profile_photo
├─ user_readiness_scores: recalculated (now 35)
├─ readiness_score_history: {"user_id": 123, "overall_score": 35, ...}
└─ user_roadmaps: created roadmap (template: "Foundation Builder")

[Day 7: Takes First Interview]
├─ interview_attempts: {"user_id": 123, "status": "Completed", ...}
├─ interview_feedback: {"overall_score": 65, "answer_quality": 68, ...}
├─ user_skill_gaps: identified gaps (React, System Design)
└─ learning_resources: linked resources for gaps

[Day 14: Weekly Update Runs]
├─ readiness_score_history: new entry (score: 38)
├─ user_readiness_scores: updated (now 38, trend_bonus: 0)
├─ user_roadmap_progress: tasks marked complete
└─ Email: "You improved to 38/100!"

[Day 42: Completes Roadmap]
├─ user_readiness_scores: (now 62, status: "In Progress")
├─ user_roadmaps: status "Completed"
├─ user_skill_gaps: React now "In Progress" (was "Missing")
└─ Email: "You're ready for interviews! Apply now."

[Day 60: Gets Job Offer]
├─ user_readiness_scores: (now 78, status: "Job Ready")
├─ Success story recorded
└─ Referral reward activated
```

---

## 🧪 TESTING SCENARIOS

### Scenario 1: New User

```
1. Sign up as fresh graduate
   - Expected: Score 25/100 (Early)
   
2. Complete profile
   - Expected: Score 35/100 (Early)
   
3. Upload resume
   - Expected: Score 45/100 (Early)
   
4. Take aptitude test
   - Expected: Score 50/100 (In Progress)
   
5. Do first interview
   - Expected: Score 62/100 (In Progress)
   - Get feedback + roadmap
   
6. Follow roadmap (2 weeks)
   - Expected: Score 72/100 (Ready)
   - Invitations to apply to jobs
```

### Scenario 2: Career Switcher

```
1. Sign up with 5 years experience (different field)
   - Expected: Score 40/100 (Early) - Experience doesn't count
   
2. Update resume with target role
   - Expected: Score 45/100 (Early)
   - Skill gaps identified (new role)
   
3. Take aptitude test
   - Expected: Score 55/100 (In Progress) - Good logical base
   
4. Do interviews (multiple times)
   - Expected: Score 68/100 (Ready)
   - Guidance on soft skills transition
   
5. Learn new skills (4 weeks)
   - Expected: Score 78/100 (Ready)
   - Ready for mid-level jobs
```

### Scenario 3: Struggling User

```
1. Sign up
   - Expected: Score 28/100
   
2. Not active for 2 weeks
   - Expected: Score 28 → 18/100 (penalty)
   - Email: "We miss you!"
   
3. Returns, sees low score
   - Expected: Motivation to start
   
4. Completes week 1 of roadmap
   - Expected: Score 20 → 35/100 (+15)
   - Email: "Great comeback! 🎉"
   
5. Continues for 8 weeks
   - Expected: Score 35 → 72/100 (+37)
   - Feeling of achievement
   - Ready to apply
```

---

## 📊 CACHING STRATEGY

```
Cache Layer:

1. Readiness Score
   - Duration: 1 hour
   - Invalidate: On manual update, after cron run
   - Key: "user:{user_id}:readiness_score"

2. Skill Gaps
   - Duration: 24 hours
   - Invalidate: On skill update, after job sync
   - Key: "user:{user_id}:skill_gaps:{role}"

3. Roadmap
   - Duration: 12 hours
   - Invalidate: On task complete
   - Key: "roadmap:{roadmap_id}"

4. Resources
   - Duration: 7 days
   - Invalidate: On resource update
   - Key: "resources:skill:{skill_id}"

5. User Session
   - Duration: 24 hours
   - Invalidate: On logout
   - Key: "session:{session_id}"
```

---

This guide ensures smooth integration of all premium features. Use this as reference during implementation! 🚀
