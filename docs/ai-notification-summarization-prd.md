# Product Requirements Document: AI-Powered Notification Summarization

## 1. Executive Summary
Enhance the existing WeCoza Events Plugin notification system by integrating OpenAI API to generate intelligent, human-readable summaries of class change events. The system will replace raw JSON data in email notifications with AI-generated insights while maintaining full audit trail capabilities.

## 2. Current System Analysis

### 2.1 Database Schema
From `wecoza_db_schema_bu_oct_22.sql`, the `public.class_change_logs` table currently has:
```sql
CREATE TABLE public.class_change_logs (
    log_id bigint NOT NULL,
    class_id integer,
    operation text NOT NULL,
    changed_at timestamp without time zone DEFAULT now() NOT NULL,
    new_row jsonb,
    old_row jsonb,
    diff jsonb,
    tasks jsonb DEFAULT '[]'::jsonb
);
```

### 2.2 Notification Flow
- **Trigger**: `public.log_class_change()` captures INSERT/UPDATE operations
- **Storage**: Audit trail in `class_change_logs` with full JSON diff data
- **Processing**: `NotificationProcessor` runs via WordPress cron every 5 minutes
- **Current Email**: Raw JSON data (operation, changes, full row snapshot)
- **Tracking**: `wecoza_last_notified_log_id` option prevents duplicate notifications

### 2.3 Prompt Templates
- **INSERT**: `docs/insert-prompt.md` - QA validation for new classes
- **UPDATE**: `docs/update-prompt.md` - Change intent validation for updates
- **Examples**: Sample notification data in `docs/notification-*-example.json`

## 3. Technical Requirements

### 3.1 Database Schema Enhancement
**New Column**: `ai_summary` with JSONB data type
```sql
ALTER TABLE public.class_change_logs 
ADD COLUMN ai_summary JSONB;
```

**JSON Structure**: 
```json
{
  "summary": "AI-generated text summary of the class change",
  "viewed": false,
  "generated_at": "2025-10-27T10:30:00Z", 
  "model": "gpt-4",
  "tokens_used": 150,
  "processing_time_ms": 2300
}
```

**Migration Strategy**: 
- Column allows NULL for backward compatibility
- Manual column addition as specified by user
- Historical data processing script for existing records

### 3.2 OpenAI Integration Architecture
- **API Key Storage**: WordPress option `wecoza_openai_api_key`
- **Service Class**: New `AISummaryService.php` in `includes/Services/`
- **Model**: GPT-4 for optimal quality with GPT-3.5-turbo fallback
- **Processing Mode**: Synchronous (blocking) to ensure summary ready before email
- **Retry Logic**: 3 attempts with exponential backoff (1s, 2s, 4s)
- **Error Handling**: Fallback to raw JSON if AI generation fails

### 3.3 Workflow Implementation

```
Class Change → PostgreSQL Trigger → class_change_logs table
                                                    ↓
WordPress Cron (every 5min) → NotificationProcessor::process()
                                                    ↓
                                      AISummaryService::generateSummary()
                                                    ↓
                                   OpenAI API Call (INSERT or UPDATE prompt)
                                                    ↓
                              Store result in ai_summary column
                                                    ↓
                               Generate email with AI summary (or fallback)
                                                    ↓
                                     Update wecoza_last_notified_log_id
                                                    ↓
                                          Send notification via wp_mail()
```

### 3.4 Email Content Transformation

**Current Email Structure**:
```
Subject: [WeCoza] Class insert: 54 (5-AET-COMM_NUM-2025-10-02-07-51)

Operation: INSERT
Changed At: 2025-10-22 10:32:16.246526
Class ID: 54
Class Code: 5-AET-COMM_NUM-2025-10-02-07-51
Class Subject: COMM_NUM

Changes:
{...raw JSON...}

New Row Snapshot:
{...raw JSON...}
```

**Enhanced Email Structure**:
```
Subject: [WeCoza] Class insert: 54 (5-AET-COMM_NUM-2025-10-02-07-51)

🤖 AI Summary:
A new AET Communications Numeracy class (5-AET-COMM_NUM-2025-10-02-07-51) has been created for Client ID 5 at the Bloemfontein East location. The class is scheduled to run weekly on Wednesdays and Fridays from 08:00-10:00 starting October 23, 2025, through January 1, 2027. Three learners (John Doe - CL4, Sarah Wilson - NL4, David Brown) are currently enrolled. The schedule includes holiday exceptions for December 26, 2025.

---
📋 Class Details:
Operation: INSERT | Changed At: 2025-10-22 10:32:16
Class ID: 54 | Subject: COMM_NUM | Duration: 240 minutes

🔗 View Full Audit Data: [Link to admin dashboard]
```

## 4. Implementation Plan

### Phase 1: Database & Infrastructure (Week 1)
1. **Database Schema**: User manually adds `ai_summary` column
2. **OpenAI Service**: Create `AISummaryService.php` with:
   - OpenAI client integration
   - Prompt template loading from `/docs/`
   - Error handling and retry logic
   - Token usage tracking
3. **Configuration**: Add OpenAI API key option to WordPress admin

### Phase 2: Notification Enhancement (Week 2)
1. **NotificationProcessor Updates**:
   - Integrate AI service call before email generation
   - Update email template to use AI summaries
   - Implement fallback mechanism for API failures
   - Add processing metrics logging
2. **Email Template Redesign**:
   - Human-readable format with AI summary
   - Metadata section for quick reference
   - Link to full audit data in admin dashboard

### Phase 3: Historical Data & Testing (Week 3)
1. **Batch Processing Script**:
   - Process existing `class_change_logs` records
   - Use `wecoza_last_notified_log_id` as cutoff point
   - Progress tracking and resume capability
2. **Testing Framework**:
   - Unit tests for AI service
   - Integration tests with both INSERT/UPDATE scenarios
   - Performance testing for synchronous processing

### Phase 4: Monitoring & Optimization (Week 4)
1. **Monitoring Dashboard**:
   - AI processing success rate
   - Token usage and cost tracking
   - Processing time metrics
2. **Optimization**:
   - Caching for repeated similar changes
   - Prompt optimization based on results
   - Cost analysis and model selection

## 5. Data Models & API Contracts

### 5.1 AISummaryService Interface
```php
interface AISummaryService {
    public function generateSummary(array $logEntry): array;
    public function processHistoricalRecords(int $fromLogId, int $toLogId): void;
    public function getProcessingMetrics(): array;
}
```

### 5.2 Enhanced NotificationProcessor
```php
class NotificationProcessor {
    private function processLogEntry(array $row): void {
        // Generate AI summary
        $aiSummary = $this->aiSummaryService->generateSummary($row);
        
        // Store in database
        $this->updateAiSummary($row['log_id'], $aiSummary);
        
        // Generate enhanced email
        $emailContent = $this->buildEnhancedEmail($row, $aiSummary);
        
        // Send notification
        $this->sendNotification($emailContent, $recipient);
    }
}
```

## 6. Security & Privacy Considerations
- **API Key Security**: Encrypted storage in WordPress options
- **Data Privacy**: No sensitive learner PII sent to OpenAI beyond what's in notifications
- **Audit Trail**: Full JSON data preserved regardless of AI processing
- **Rate Limiting**: Implement OpenAI API rate limiting controls
- **Error Logging**: Sanitized logs without exposing sensitive data

## 7. Performance Impact Analysis
- **Processing Time**: +2-4 seconds per notification for AI generation
- **Database Impact**: Minimal (additional JSONB column)
- **API Costs**: Estimated $0.002-0.008 per notification (GPT-4)
- **Memory Usage**: +10-20MB during AI processing
- **Network**: Additional HTTPS call to OpenAI API

## 8. Success Metrics & KPIs
- **User Experience**: 80%+ reduction in clarification requests
- **Processing Success Rate**: >95% AI generation success
- **Cost Efficiency**: < $0.01 per notification
- **Performance**: < 5 seconds total processing time
- **Coverage**: 100% of new notifications have AI summaries

## 9. Rollout Strategy
1. **Feature Flag**: `wecoza_ai_summaries_enabled` WordPress option
2. **Gradual Rollout**: Start with 10% of notifications, increase to 100%
3. **Monitoring**: Real-time error rate and cost monitoring
4. **Rollback**: Immediate fallback to raw JSON if issues detected
5. **User Feedback**: Collection mechanism in admin dashboard

## 10. Risk Mitigation
- **API Outages**: Automatic fallback to raw JSON
- **Cost Overrun**: Daily/weekly cost limits with alerts
- **Quality Issues**: Human review pipeline for critical notifications
- **Performance**: Timeout controls and async option for future

## 11. Dependencies & Prerequisites
- **Manual Database Change**: `ALTER TABLE public.class_change_logs ADD COLUMN ai_summary JSONB;`
- **OpenAI API Key**: Must be configured in WordPress options
- **WordPress Environment**: PHP 8.1+ with cURL extension
- **Network Access**: HTTPS connectivity to OpenAI API

## 12. Testing Requirements
- **Unit Tests**: AI service methods and error handling
- **Integration Tests**: End-to-end notification flow with AI
- **Performance Tests**: Load testing with concurrent notifications
- **User Acceptance Testing**: Email content quality and readability

## 13. Documentation & Training
- **Technical Documentation**: API integration and configuration
- **User Guide**: How to enable/disable AI summaries
- **Admin Guide**: Monitoring and troubleshooting
- **Developer Guide**: Extending and customizing prompts

This PRD provides a comprehensive roadmap for implementing AI-powered notification summaries while maintaining system reliability, security, and cost-effectiveness.
