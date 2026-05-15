# TrueOcc Premium Features — Quick Start Architecture Guide

This guide contains ready-to-use code scaffolding and architecture patterns to accelerate implementation.

---

## SECTION 1: DATABASE SCHEMA ADDITIONS

### 1.1 Run This SQL to Add New Tables

```sql
-- ============================================================
-- Premium Features Database Extensions
-- ============================================================

-- Career Readiness Scoring
CREATE TABLE IF NOT EXISTS user_readiness_scores (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED UNIQUE NOT NULL,
  overall_score INT DEFAULT 0,
  resume_quality_score INT DEFAULT 0,
  profile_completeness_score INT DEFAULT 0,
  aptitude_score INT DEFAULT 0,
  interview_score INT DEFAULT 0,
  skill_match_score INT DEFAULT 0,
  consistency_score INT DEFAULT 0,
  trend_bonus INT DEFAULT 0,
  final_score INT DEFAULT 0,
  status ENUM('Early','In Progress','Ready','Highly Ready') DEFAULT 'Early',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (overall_score, status)
);

CREATE TABLE IF NOT EXISTS readiness_score_history (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  overall_score INT DEFAULT 0,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, recorded_at)
);

-- Learning Resources
CREATE TABLE IF NOT EXISTS learning_resources (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100),
  resource_type ENUM('Book','Course','Tutorial','Project','Tool','Article') DEFAULT 'Book',
  url VARCHAR(255),
  platform VARCHAR(100),
  difficulty ENUM('Beginner','Intermediate','Advanced'),
  estimated_hours INT DEFAULT 0,
  rating DECIMAL(3,2) DEFAULT 4.5,
  reviews_count INT DEFAULT 0,
  is_verified BOOLEAN DEFAULT 0,
  is_free BOOLEAN DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category, difficulty, rating)
);

-- Interview Analytics
CREATE TABLE IF NOT EXISTS interview_attempts (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED,
  attempt_number INT DEFAULT 1,
  start_time TIMESTAMP,
  end_time TIMESTAMP,
  total_duration_seconds INT DEFAULT 0,
  is_retake BOOLEAN DEFAULT 0,
  recording_url VARCHAR(255),
  status ENUM('In Progress','Completed','Abandoned') DEFAULT 'In Progress',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  INDEX (user_id, status)
);

CREATE TABLE IF NOT EXISTS interview_feedback (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  attempt_id INT UNSIGNED NOT NULL UNIQUE,
  answer_quality_score INT DEFAULT 0,
  communication_score INT DEFAULT 0,
  confidence_score INT DEFAULT 0,
  discipline_score INT DEFAULT 0,
  consistency_score INT DEFAULT 0,
  overall_score INT DEFAULT 0,
  feedback_json JSON,
  filler_word_count INT DEFAULT 0,
  head_violations INT DEFAULT 0,
  tab_switches INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (attempt_id) REFERENCES interview_attempts(id) ON DELETE CASCADE
);

-- Skill Gap Analysis
CREATE TABLE IF NOT EXISTS user_skill_gaps (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  target_role VARCHAR(100),
  skill_name VARCHAR(100) NOT NULL,
  gap_type ENUM('Strong','Medium','Missing') DEFAULT 'Missing',
  current_level ENUM('None','Beginner','Intermediate','Advanced','Expert') DEFAULT 'None',
  required_level ENUM('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
  priority INT DEFAULT 0,
  jobs_count INT DEFAULT 0,
  jobs_percentage INT DEFAULT 0,
  estimated_learning_hours INT DEFAULT 0,
  progress_percent INT DEFAULT 0,
  status ENUM('Not Started','In Progress','Completed') DEFAULT 'Not Started',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, target_role, gap_type)
);

-- Weekly Roadmap
CREATE TABLE IF NOT EXISTS roadmap_templates (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE,
  duration_weeks INT DEFAULT 6,
  min_readiness_score INT DEFAULT 0,
  max_readiness_score INT DEFAULT 100,
  expected_improvement INT DEFAULT 15,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS roadmap_weeks (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id INT UNSIGNED NOT NULL,
  week_number INT NOT NULL,
  title VARCHAR(150),
  description TEXT,
  priority ENUM('Critical','High','Medium','Low') DEFAULT 'High',
  expected_score_gain INT DEFAULT 0,
  focus_areas JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES roadmap_templates(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS roadmap_tasks (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  week_id INT UNSIGNED NOT NULL,
  task_order INT DEFAULT 0,
  title VARCHAR(200),
  description TEXT,
  task_type ENUM('Read','Course','Practice','Interview','Apply','Project','Review') DEFAULT 'Read',
  estimated_hours INT DEFAULT 0,
  resource_links JSON,
  is_required BOOLEAN DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (week_id) REFERENCES roadmap_weeks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_roadmaps (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  target_role VARCHAR(100),
  start_date DATE,
  end_date DATE,
  current_week INT DEFAULT 1,
  status ENUM('Active','Paused','Completed','Abandoned') DEFAULT 'Active',
  readiness_on_start INT DEFAULT 0,
  readiness_on_end INT DEFAULT 0,
  completion_percent INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES roadmap_templates(id),
  INDEX (user_id, status)
);

CREATE TABLE IF NOT EXISTS user_roadmap_progress (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  roadmap_id INT UNSIGNED NOT NULL,
  week_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  status ENUM('Not Started','In Progress','Completed','Skipped') DEFAULT 'Not Started',
  completed_at TIMESTAMP NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (roadmap_id) REFERENCES user_roadmaps(id) ON DELETE CASCADE,
  FOREIGN KEY (week_id) REFERENCES roadmap_weeks(id),
  FOREIGN KEY (task_id) REFERENCES roadmap_tasks(id)
);
```

---

## SECTION 2: BACKEND API SCAFFOLDING

### 2.1 New File: `/backend/includes/score-calculator.php`

```php
<?php
// ============================================================
// Career Readiness Score Calculator
// ============================================================

class ReadinessScoreCalculator {
  private $db;
  
  public function __construct($database) {
    $this->db = $database;
  }

  /**
   * Calculate complete readiness score for a user
   */
  public function calculateScore($userId) {
    $resumeScore = $this->getResumeQualityScore($userId);
    $profileScore = $this->getProfileCompletenessScore($userId);
    $aptitudeScore = $this->getAptitudeScore($userId);
    $interviewScore = $this->getInterviewScore($userId);
    $skillMatchScore = $this->getSkillMatchScore($userId);
    $consistencyScore = $this->getConsistencyScore($userId);
    
    // Weighted calculation
    $finalScore = 
      ($resumeScore * 0.20) +
      ($profileScore * 0.15) +
      ($aptitudeScore * 0.20) +
      ($interviewScore * 0.20) +
      ($skillMatchScore * 0.15) +
      ($consistencyScore * 0.10);
    
    // Check for trend bonus/penalty
    $trendBonus = $this->calculateTrendBonus($userId);
    $finalScore = min(100, max(0, $finalScore + $trendBonus));
    
    // Determine status
    $status = $this->getStatusFromScore($finalScore);
    
    return [
      'overall_score' => round($finalScore),
      'resume_quality_score' => $resumeScore,
      'profile_completeness_score' => $profileScore,
      'aptitude_score' => $aptitudeScore,
      'interview_score' => $interviewScore,
      'skill_match_score' => $skillMatchScore,
      'consistency_score' => $consistencyScore,
      'trend_bonus' => $trendBonus,
      'final_score' => round($finalScore),
      'status' => $status
    ];
  }

  /**
   * Resume Quality Score (0-100)
   */
  private function getResumeQualityScore($userId) {
    $score = 0;
    
    try {
      $resume = $this->db->fetchOne(
        "SELECT * FROM resumes WHERE user_id = ?",
        [$userId]
      );
      
      if (!$resume) return 0;
      
      // Has all sections
      if ($resume['full_name'] && $resume['email'] && $resume['phone']) $score += 25;
      
      // Has summary
      if ($resume['summary'] && strlen($resume['summary']) > 50) $score += 15;
      
      // Has experience
      if ($resume['experience'] && count(json_decode($resume['experience'] ?? '[]')) >= 2) $score += 20;
      
      // Has skills
      $skills = count(explode(',', $resume['skills'] ?? ''));
      if ($skills >= 5) $score += 20;
      
      // Has links
      if ($resume['linkedin_url'] || $resume['github_url']) $score += 15;
      
      // Check for grammar (simple check)
      $text = $resume['summary'] . ' ' . $resume['experience'] ?? '';
      if (!preg_match('/your|u\'/i', $text)) $score += 5; // Very basic grammar check
      
      return min(100, $score);
    } catch (Exception $e) {
      error_log("Error in getResumeQualityScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Profile Completeness Score (0-100)
   */
  private function getProfileCompletenessScore($userId) {
    $score = 0;
    
    try {
      $user = $this->db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$userId]
      );
      
      if (!$user) return 0;
      
      if ($user['name']) $score += 15;
      if ($user['email']) $score += 15;
      if ($user['phone'] && $user['location']) $score += 15;
      if ($user['profile_photo']) $score += 15;
      
      // Check metadata
      $meta = $this->db->fetchOne(
        "SELECT * FROM user_metadata WHERE user_id = ?",
        [$userId]
      );
      
      if ($meta) {
        if ($meta['target_role']) $score += 15;
        if ($meta['bio'] && strlen($meta['bio']) > 50) $score += 15;
      }
      
      return min(100, $score);
    } catch (Exception $e) {
      error_log("Error in getProfileCompletenessScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Aptitude Test Score (0-100)
   */
  private function getAptitudeScore($userId) {
    try {
      $tests = $this->db->fetchAll(
        "SELECT score FROM test_results 
         WHERE user_id = ? AND score > 0 
         ORDER BY created_at DESC LIMIT 3",
        [$userId]
      );
      
      if (empty($tests)) return 0;
      
      $avgScore = array_sum(array_column($tests, 'score')) / count($tests);
      return min(100, round($avgScore));
    } catch (Exception $e) {
      error_log("Error in getAptitudeScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Interview Score (0-100)
   */
  private function getInterviewScore($userId) {
    try {
      $interviews = $this->db->fetchAll(
        "SELECT if.overall_score FROM interview_feedback if
         JOIN interview_attempts ia ON if.attempt_id = ia.id
         WHERE ia.user_id = ? AND ia.status = 'Completed'
         ORDER BY ia.created_at DESC LIMIT 3",
        [$userId]
      );
      
      if (empty($interviews)) return 0;
      
      $avgScore = array_sum(array_column($interviews, 'overall_score')) / count($interviews);
      return min(100, round($avgScore));
    } catch (Exception $e) {
      error_log("Error in getInterviewScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Skill Match Score (0-100)
   */
  private function getSkillMatchScore($userId) {
    try {
      $meta = $this->db->fetchOne(
        "SELECT target_role FROM user_metadata WHERE user_id = ?",
        [$userId]
      );
      
      if (!$meta || !$meta['target_role']) return 0;
      
      // Get user's skills from resume
      $resume = $this->db->fetchOne(
        "SELECT skills FROM resumes WHERE user_id = ?",
        [$userId]
      );
      
      $userSkills = array_map('trim', explode(',', $resume['skills'] ?? ''));
      
      // Get top jobs in that role
      $jobs = $this->db->fetchAll(
        "SELECT skills_required FROM jobs 
         WHERE LOWER(title) LIKE ? 
         LIMIT 10",
        ['%' . strtolower($meta['target_role']) . '%']
      );
      
      if (empty($jobs)) return 0;
      
      $allRequiredSkills = [];
      foreach ($jobs as $job) {
        $required = array_map('trim', explode(',', $job['skills_required'] ?? ''));
        $allRequiredSkills = array_merge($allRequiredSkills, $required);
      }
      
      // Calculate match percentage
      $matches = array_intersect($userSkills, $allRequiredSkills);
      $matchPercent = !empty($allRequiredSkills) ? 
        (count($matches) / count(array_unique($allRequiredSkills))) * 100 : 0;
      
      return min(100, round($matchPercent));
    } catch (Exception $e) {
      error_log("Error in getSkillMatchScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Consistency/Activity Score (0-100)
   */
  private function getConsistencyScore($userId) {
    $score = 0;
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    try {
      // Logins in last 30 days
      $logins = $this->db->fetchOne(
        "SELECT COUNT(*) as count FROM user_sessions 
         WHERE user_id = ? AND last_activity > ?",
        [$userId, $thirtyDaysAgo]
      );
      
      if (($logins['count'] ?? 0) >= 10) $score += 20;
      
      // Tests attempted
      $tests = $this->db->fetchOne(
        "SELECT COUNT(*) as count FROM test_results 
         WHERE user_id = ? AND created_at > ?",
        [$userId, $thirtyDaysAgo]
      );
      
      if (($tests['count'] ?? 0) >= 2) $score += 20;
      
      // Interviews
      $interviews = $this->db->fetchOne(
        "SELECT COUNT(*) as count FROM interview_attempts 
         WHERE user_id = ? AND created_at > ?",
        [$userId, $thirtyDaysAgo]
      );
      
      if (($interviews['count'] ?? 0) >= 2) $score += 20;
      
      // Resume updated
      $resume = $this->db->fetchOne(
        "SELECT updated_at FROM resumes 
         WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1",
        [$userId]
      );
      
      if ($resume && strtotime($resume['updated_at']) > strtotime($thirtyDaysAgo)) $score += 20;
      
      // Job applications
      $applications = $this->db->fetchOne(
        "SELECT COUNT(*) as count FROM job_applications 
         WHERE user_id = ? AND created_at > ?",
        [$userId, $thirtyDaysAgo]
      );
      
      if (($applications['count'] ?? 0) >= 3) $score += 20;
      
      return min(100, $score);
    } catch (Exception $e) {
      error_log("Error in getConsistencyScore: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Calculate trend bonus/penalty
   */
  private function calculateTrendBonus($userId) {
    try {
      $thisWeekScore = $this->db->fetchOne(
        "SELECT overall_score FROM readiness_score_history 
         WHERE user_id = ? AND recorded_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY recorded_at ASC LIMIT 1",
        [$userId]
      );
      
      $lastWeekScore = $this->db->fetchOne(
        "SELECT overall_score FROM readiness_score_history 
         WHERE user_id = ? AND recorded_at BETWEEN 
         DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY recorded_at DESC LIMIT 1",
        [$userId]
      );
      
      if (!$thisWeekScore || !$lastWeekScore) return 0;
      
      $diff = $thisWeekScore['overall_score'] - $lastWeekScore['overall_score'];
      
      if ($diff >= 5) return 5;
      if ($diff <= -5) return -5;
      return 0;
    } catch (Exception $e) {
      error_log("Error in calculateTrendBonus: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Get status from score
   */
  private function getStatusFromScore($score) {
    if ($score >= 80) return 'Highly Ready';
    if ($score >= 60) return 'Ready';
    if ($score >= 40) return 'In Progress';
    return 'Early';
  }

  /**
   * Save score to database
   */
  public function saveScore($userId, $scoreData) {
    try {
      $this->db->query(
        "INSERT INTO user_readiness_scores 
         (user_id, overall_score, resume_quality_score, profile_completeness_score, 
          aptitude_score, interview_score, skill_match_score, consistency_score, 
          trend_bonus, final_score, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
         overall_score = VALUES(overall_score),
         resume_quality_score = VALUES(resume_quality_score),
         profile_completeness_score = VALUES(profile_completeness_score),
         aptitude_score = VALUES(aptitude_score),
         interview_score = VALUES(interview_score),
         skill_match_score = VALUES(skill_match_score),
         consistency_score = VALUES(consistency_score),
         trend_bonus = VALUES(trend_bonus),
         final_score = VALUES(final_score),
         status = VALUES(status)",
        [
          $userId,
          $scoreData['overall_score'],
          $scoreData['resume_quality_score'],
          $scoreData['profile_completeness_score'],
          $scoreData['aptitude_score'],
          $scoreData['interview_score'],
          $scoreData['skill_match_score'],
          $scoreData['consistency_score'],
          $scoreData['trend_bonus'],
          $scoreData['final_score'],
          $scoreData['status']
        ]
      );
      
      // Save to history
      $this->db->query(
        "INSERT INTO readiness_score_history (user_id, overall_score) 
         VALUES (?, ?)",
        [$userId, $scoreData['final_score']]
      );
      
      return true;
    } catch (Exception $e) {
      error_log("Error saving score: " . $e->getMessage());
      return false;
    }
  }
}
```

### 2.2 Enhanced: `/backend/api/user.php`

Add these new actions:

```php
<?php
// Add to existing user.php file

// ... existing code ...

case 'getReadinessScore':
  // Get readiness score for user
  $userId = $_SESSION['user_id'] ?? null;
  if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
  }
  
  require_once __DIR__ . '/../includes/score-calculator.php';
  $calculator = new ReadinessScoreCalculator($db);
  
  // Get from cache first
  $cached = $db->fetchOne(
    "SELECT * FROM user_readiness_scores WHERE user_id = ?",
    [$userId]
  );
  
  if ($cached) {
    echo json_encode(['success' => true, 'data' => $cached]);
  } else {
    // Calculate new score
    $score = $calculator->calculateScore($userId);
    $calculator->saveScore($userId, $score);
    echo json_encode(['success' => true, 'data' => $score]);
  }
  break;

case 'updateReadinessScore':
  // Manually recalculate score
  $userId = $_SESSION['user_id'] ?? null;
  if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
  }
  
  require_once __DIR__ . '/../includes/score-calculator.php';
  $calculator = new ReadinessScoreCalculator($db);
  
  $score = $calculator->calculateScore($userId);
  $calculator->saveScore($userId, $score);
  
  echo json_encode(['success' => true, 'data' => $score]);
  break;

case 'getSkillGaps':
  // Get skill gap analysis
  $userId = $_SESSION['user_id'] ?? null;
  $role = $_GET['role'] ?? null;
  
  if (!$userId || !$role) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
  }
  
  require_once __DIR__ . '/../includes/skill-gap-analyzer.php';
  $analyzer = new SkillGapAnalyzer($db);
  
  $gaps = $analyzer->analyzeGaps($userId, $role);
  echo json_encode(['success' => true, 'data' => $gaps]);
  break;

case 'getRoadmap':
  // Get user's roadmap
  $userId = $_SESSION['user_id'] ?? null;
  $roadmapId = $_GET['roadmap_id'] ?? null;
  
  if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
  }
  
  $roadmap = $db->fetchOne(
    "SELECT * FROM user_roadmaps 
     WHERE id = ? AND user_id = ?",
    [$roadmapId, $userId]
  );
  
  if (!$roadmap) {
    echo json_encode(['success' => false, 'message' => 'Roadmap not found']);
    exit;
  }
  
  $weeks = $db->fetchAll(
    "SELECT * FROM roadmap_weeks 
     WHERE template_id = ? 
     ORDER BY week_number",
    [$roadmap['template_id']]
  );
  
  foreach ($weeks as &$week) {
    $week['tasks'] = $db->fetchAll(
      "SELECT * FROM roadmap_tasks 
       WHERE week_id = ? 
       ORDER BY task_order",
      [$week['id']]
    );
    
    $week['progress'] = $db->fetchOne(
      "SELECT COUNT(*) as count FROM user_roadmap_progress 
       WHERE roadmap_id = ? AND week_id = ? AND status = 'Completed'",
      [$roadmapId, $week['id']]
    );
  }
  
  echo json_encode(['success' => true, 'data' => [
    'roadmap' => $roadmap,
    'weeks' => $weeks
  ]]);
  break;

case 'generateRoadmap':
  // Generate a new roadmap for user
  $userId = $_SESSION['user_id'] ?? null;
  $currentScore = $_POST['current_score'] ?? null;
  $targetRole = $_POST['target_role'] ?? null;
  
  if (!$userId || !$currentScore || !$targetRole) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
  }
  
  require_once __DIR__ . '/../includes/roadmap-generator.php';
  $generator = new RoadmapGenerator($db);
  
  $roadmapId = $generator->generateRoadmap($userId, $currentScore, $targetRole);
  
  if ($roadmapId) {
    echo json_encode(['success' => true, 'data' => ['roadmap_id' => $roadmapId]]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to generate roadmap']);
  }
  break;
```

---

## SECTION 3: FRONTEND COMPONENTS

### 3.1 New File: `/frontend/js/components/score-card.js`

```javascript
/**
 * Career Readiness Score Card Component
 */
class ScoreCard {
  constructor(containerId, options = {}) {
    this.container = document.getElementById(containerId);
    this.options = { ...this.defaultOptions(), ...options };
    this.data = null;
    this.animating = false;
  }

  defaultOptions() {
    return {
      size: 160,
      showBreakdown: true,
      clickable: true,
      animateOnLoad: true
    };
  }

  async initialize() {
    try {
      const response = await API.call('user.php?action=getReadinessScore');
      if (response.success) {
        this.data = response.data;
        this.render();
        if (this.options.animateOnLoad) {
          this.animate();
        }
      }
    } catch (error) {
      console.error('Failed to load readiness score:', error);
      this.renderError();
    }
  }

  render() {
    const { overall_score, status, resume_quality_score, aptitude_score } = this.data;
    
    this.container.innerHTML = `
      <div class="score-card">
        <div class="score-card-header">
          <h3>Career Readiness Score</h3>
          <span class="trend ${this.getTrendClass()}">
            ${this.getTrendText()}
          </span>
        </div>
        
        <div class="score-display">
          <svg class="progress-ring" width="${this.options.size}" height="${this.options.size}">
            <circle class="progress-ring-bg" 
              cx="${this.options.size/2}" 
              cy="${this.options.size/2}" 
              r="${this.options.size/2 - 8}"
              fill="none" 
              stroke="var(--border)" 
              stroke-width="8"/>
            <circle class="progress-ring-circle" 
              cx="${this.options.size/2}" 
              cy="${this.options.size/2}" 
              r="${this.options.size/2 - 8}"
              fill="none" 
              stroke="var(--primary)" 
              stroke-width="8"
              stroke-dasharray="${this.getCircumference()}"
              stroke-dashoffset="${this.getCircumference()}"/>
          </svg>
          
          <div class="score-overlay">
            <div class="score-value" data-value="${overall_score}">0</div>
            <div class="score-label">/ 100</div>
          </div>
        </div>
        
        <div class="score-status ${this.getStatusClass(status)}">
          ${this.getStatusBadge(status)} ${status}
        </div>
        
        ${this.options.showBreakdown ? this.renderBreakdown() : ''}
        
        <button class="btn btn-outline btn-sm" onclick="this.parentElement.showDetails?.()">
          View Details
        </button>
      </div>
    `;
  }

  renderBreakdown() {
    const breakdown = [
      { label: 'Resume', value: this.data.resume_quality_score },
      { label: 'Profile', value: this.data.profile_completeness_score },
      { label: 'Aptitude', value: this.data.aptitude_score },
      { label: 'Interview', value: this.data.interview_score },
      { label: 'Skills', value: this.data.skill_match_score },
      { label: 'Consistency', value: this.data.consistency_score }
    ];
    
    return `
      <div class="score-breakdown">
        ${breakdown.map(item => `
          <div class="breakdown-item">
            <span class="label">${item.label}</span>
            <div class="bar-wrapper">
              <div class="bar" style="width: ${item.value}%"></div>
            </div>
            <span class="value">${item.value}</span>
          </div>
        `).join('')}
      </div>
    `;
  }

  animate() {
    if (this.animating) return;
    this.animating = true;
    
    const circle = this.container.querySelector('.progress-ring-circle');
    const valueEl = this.container.querySelector('.score-value');
    const targetValue = parseInt(this.data.overall_score);
    
    // Animate circle
    const circumference = this.getCircumference();
    const targetOffset = circumference - (targetValue / 100) * circumference;
    
    circle.style.transition = 'stroke-dashoffset 1.5s ease-out';
    circle.style.strokeDashoffset = targetOffset;
    
    // Animate number
    this.animateCounter(valueEl, 0, targetValue, 1500);
  }

  animateCounter(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
      current += increment;
      if (current >= end) {
        current = end;
        clearInterval(timer);
      }
      element.textContent = Math.floor(current);
    }, 16);
  }

  getCircumference() {
    const radius = this.options.size / 2 - 8;
    return 2 * Math.PI * radius;
  }

  getTrendClass() {
    const trend = this.data.trend_bonus;
    if (trend > 0) return 'trend-up';
    if (trend < 0) return 'trend-down';
    return 'trend-flat';
  }

  getTrendText() {
    const bonus = this.data.trend_bonus;
    if (bonus > 0) return `↑ +${bonus} this week`;
    if (bonus < 0) return `↓ ${bonus} this week`;
    return '→ Stable';
  }

  getStatusClass(status) {
    const classMap = {
      'Early': 'status-early',
      'In Progress': 'status-progress',
      'Ready': 'status-ready',
      'Highly Ready': 'status-excellent'
    };
    return classMap[status] || '';
  }

  getStatusBadge(status) {
    const badges = {
      'Early': '🔵',
      'In Progress': '🟡',
      'Ready': '🟢',
      'Highly Ready': '✨'
    };
    return badges[status] || '';
  }

  renderError() {
    this.container.innerHTML = `
      <div class="card error">
        <p>Failed to load readiness score. Please refresh the page.</p>
      </div>
    `;
  }

  showDetails() {
    window.location.href = '/frontend/pages/readiness-dashboard.html';
  }
}

// Usage
document.addEventListener('DOMContentLoaded', () => {
  const scoreCard = new ScoreCard('score-container', {
    size: 160,
    showBreakdown: true,
    animateOnLoad: true
  });
  scoreCard.initialize();
});
```

### 3.2 New CSS: `/frontend/css/components/readiness-score.css`

```css
/* Score Card Styles */
.score-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  padding: 24px;
  box-shadow: var(--shadow);
  transition: var(--t);
}

.score-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}

.score-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.score-card-header h3 {
  font-family: 'Syne', sans-serif;
  font-size: 17px;
  font-weight: 700;
  margin: 0;
}

.trend {
  font-size: 13px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: var(--r-sm);
}

.trend-up {
  color: var(--success);
  background: rgba(34, 197, 94, 0.1);
}

.trend-down {
  color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}

.trend-flat {
  color: var(--muted);
  background: rgba(0, 0, 0, 0.05);
}

/* Score Display */
.score-display {
  position: relative;
  width: fit-content;
  margin: 24px auto;
}

.progress-ring {
  transform: rotate(-90deg);
}

.progress-ring-bg {
  opacity: 0.1;
}

.progress-ring-circle {
  stroke-linecap: round;
  transition: stroke-dashoffset 0.35s;
}

.score-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}

.score-value {
  font-size: 40px;
  font-weight: 700;
  color: var(--primary);
  line-height: 1;
}

.score-label {
  font-size: 14px;
  color: var(--muted);
  margin-top: 2px;
}

/* Score Status */
.score-status {
  text-align: center;
  padding: 12px;
  border-radius: var(--r-sm);
  font-size: 14px;
  font-weight: 600;
  margin: 16px 0;
}

.score-status.status-early {
  background: rgba(107, 114, 128, 0.1);
  color: #666;
}

.score-status.status-progress {
  background: rgba(59, 130, 246, 0.1);
  color: var(--primary);
}

.score-status.status-ready {
  background: rgba(34, 197, 94, 0.1);
  color: var(--success);
}

.score-status.status-excellent {
  background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(59, 130, 246, 0.1));
  color: var(--success);
}

/* Score Breakdown */
.score-breakdown {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.breakdown-item {
  display: grid;
  grid-template-columns: 80px 1fr 40px;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.breakdown-item:last-child {
  margin-bottom: 0;
}

.breakdown-item .label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text);
}

.bar-wrapper {
  height: 6px;
  background: var(--border);
  border-radius: 3px;
  overflow: hidden;
}

.bar {
  height: 100%;
  background: linear-gradient(90deg, var(--primary), var(--accent));
  border-radius: 3px;
  transition: width 1s ease-out;
}

.breakdown-item .value {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  text-align: right;
}

/* Animations */
@keyframes scoreIncrease {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.score-card.updated .score-value {
  animation: scoreIncrease 0.6s ease-out;
}

/* Mobile */
@media (max-width: 768px) {
  .score-card {
    padding: 20px 16px;
  }

  .score-display {
    margin: 16px auto;
  }

  .score-value {
    font-size: 32px;
  }

  .breakdown-item {
    grid-template-columns: 70px 1fr 35px;
  }
}
```

---

## SECTION 4: CRON JOBS

### 4.1 New File: `/backend/cron/update-readiness-scores.php`

```php
<?php
/**
 * Weekly Readiness Score Update
 * Run every Monday at 2 AM via cron: 0 2 * * 1 /usr/bin/php /path/to/cron/update-readiness-scores.php
 */

require_once __DIR__ . '/../includes/config.php';

try {
  $db = new PDO($dsn, DB_USER, DB_PASS);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  require_once __DIR__ . '/../includes/score-calculator.php';
  $calculator = new ReadinessScoreCalculator($db);
  
  // Get all active users
  $stmt = $db->prepare("SELECT id, email FROM users WHERE is_active = 1");
  $stmt->execute();
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $updated = 0;
  $improved = 0;
  
  foreach ($users as $user) {
    // Calculate new score
    $score = $calculator->calculateScore($user['id']);
    $calculator->saveScore($user['id'], $score);
    $updated++;
    
    // Check for improvements
    if ($score['trend_bonus'] > 0) {
      $improved++;
      // Send improvement email
      sendImprovementEmail($user['email'], $score);
    }
  }
  
  // Log the update
  error_log(sprintf(
    "[%s] Readiness scores updated: %d users, %d improved",
    date('Y-m-d H:i:s'),
    $updated,
    $improved
  ));
  
  echo "✓ Updated $updated users, $improved improved\n";
  
} catch (Exception $e) {
  error_log("Cron error: " . $e->getMessage());
  echo "✗ Error: " . $e->getMessage() . "\n";
  exit(1);
}

function sendImprovementEmail($email, $score) {
  // Send email using your email service
  // $mailService->send($email, 'You improved!', '...');
}
```

---

## SECTION 5: BOOTSTRAP SCRIPT

### 5.1 Run This to Bootstrap New Features

```bash
#!/bin/bash
# Save as: /setup-premium-features.sh
# Run with: bash setup-premium-features.sh

echo "🚀 TrueOcc Premium Features Bootstrap"
echo "=================================="

# 1. Create directories
echo "📁 Creating directories..."
mkdir -p backend/cron
mkdir -p frontend/js/modules
mkdir -p frontend/js/components
mkdir -p frontend/css/components

# 2. Create base files
echo "📝 Creating base files..."
touch backend/includes/score-calculator.php
touch backend/includes/skill-gap-analyzer.php
touch backend/includes/roadmap-generator.php
touch backend/cron/update-readiness-scores.php
touch frontend/js/modules/readiness-score.js
touch frontend/js/components/score-card.js
touch frontend/css/components/readiness-score.css

# 3. Create sample HTML page
cat > frontend/pages/readiness-dashboard.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Career Readiness - TrueOcc</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/components/readiness-score.css">
</head>
<body>
  <nav class="nav">
    <div class="wrap">
      <div class="nav-brand">True<span>Occ</span></div>
      <div class="nav-links">
        <a href="user-dashboard.html">Dashboard</a>
        <a href="readiness-dashboard.html" class="active">Readiness</a>
      </div>
    </div>
  </nav>

  <div class="page">
    <div class="container">
      <h1>Your Career Readiness</h1>
      <div id="score-container"></div>
    </div>
  </div>

  <script src="../js/main.js"></script>
  <script src="../js/components/score-card.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const scoreCard = new ScoreCard('score-container');
      scoreCard.initialize();
    });
  </script>
</body>
</html>
EOF

echo "✅ Bootstrap complete!"
echo ""
echo "Next steps:"
echo "1. Import database schema: mysql -u root true_occupation < database/schema-premium.sql"
echo "2. Copy code files from this guide into your project"
echo "3. Update config.php with your database settings"
echo "4. Test the readiness score: Visit /frontend/pages/readiness-dashboard.html"
echo ""
echo "📚 Read PREMIUM_FEATURES_ROADMAP.md for complete implementation guide"
```

---

## SECTION 6: QUICK REFERENCE TABLES

### Database Tables Quick Reference

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `user_readiness_scores` | Stores calculated scores | overall_score, status, breakdown fields |
| `readiness_score_history` | Score tracking over time | user_id, overall_score, recorded_at |
| `user_skill_gaps` | Skill gap analysis | skill_name, gap_type, priority, current_level |
| `learning_resources` | Books, courses, etc. | title, type, difficulty, rating, url |
| `interview_attempts` | Interview sessions | user_id, job_id, status, recording_url |
| `interview_feedback` | AI feedback on interviews | overall_score, feedback_json |
| `user_roadmaps` | Roadmap instances | user_id, template_id, current_week, status |
| `roadmap_weeks` | Week templates | week_number, title, tasks, expected_gain |
| `roadmap_tasks` | Individual tasks | task_type, estimated_hours, resources |

### API Endpoints Summary

```
GET  /api/user.php?action=getReadinessScore
POST /api/user.php?action=updateReadinessScore
GET  /api/user.php?action=getSkillGaps&role=...
GET  /api/user.php?action=getRoadmap&roadmap_id=...
POST /api/user.php?action=generateRoadmap
POST /api/interview.php?action=submitInterview (enhanced)
GET  /api/resources.php?action=getRecommended
```

### Component Quick Start

```javascript
// Load score card
<div id="score-container"></div>
<script src="/js/components/score-card.js"></script>
<script>
  new ScoreCard('score-container').initialize();
</script>

// Load skill gaps
<div id="skill-gaps-container"></div>
<script src="/js/modules/skill-gaps.js"></script>
<script>
  new SkillGapsModule('skill-gaps-container').initialize();
</script>

// Load roadmap
<div id="roadmap-container"></div>
<script src="/js/modules/roadmap.js"></script>
<script>
  new RoadmapModule('roadmap-container').load(roadmapId);
</script>
```

---

## SECTION 7: TESTING CHECKLIST

### Before Going Live

- [ ] Database schema imported successfully
- [ ] API endpoints tested (Postman/cURL)
- [ ] Score calculation logic verified
- [ ] Frontend components render without errors
- [ ] Mobile responsive design checked
- [ ] Performance tested (load times < 2s)
- [ ] Security audit passed
- [ ] User flows tested end-to-end
- [ ] Cron jobs scheduled
- [ ] Backup strategy in place
- [ ] Error logging configured
- [ ] Analytics tracking added

---

**Ready to build? Start with Phase 1 (6 weeks) and follow the build priority roadmap in PREMIUM_FEATURES_ROADMAP.md** 🚀
