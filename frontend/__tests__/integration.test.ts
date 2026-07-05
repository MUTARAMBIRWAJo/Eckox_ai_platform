import { AIAPI } from '../lib/api/ai.api';

describe('Frontend Integration & Telemetry Reconstructors', () => {
  it('correctly retrieves and maps escalated queue records with reasons', async () => {
    const res = await AIAPI.getEscalations();
    expect(res.success).toBe(true);
    expect(res.data).toBeDefined();
    expect(res.data?.length).toBeGreaterThan(0);

    const first = res.data?.[0];
    expect(first?.reason).toBeDefined();
    expect(['injection_detected', 'guardrail_failure', 'legal_risk', 'low_confidence', 'tool_error']).toContain(first?.reason);
  });

  it('verifies trace logs reconstruct node paths and latency arrays correctly', async () => {
    const traceId = 'trace_101';
    const res = await AIAPI.getTrace(traceId);
    expect(res.success).toBe(true);
    expect(res.data).toBeDefined();

    const trace = res.data;
    expect(trace?.traceId).toBe(traceId);
    expect(trace?.nodePath).toContain('llm_reasoning');
    expect(trace?.latencyMs['llm_reasoning']).toBeGreaterThan(0);
  });

  it('confirms semantic tester retrieval payload format', async () => {
    const res = await AIAPI.testKBQuery('ce certification');
    expect(res.success).toBe(true);
    expect(res.data).toBeDefined();
    expect(res.data?.[0].score).toBeGreaterThan(0.5);
  });
});
