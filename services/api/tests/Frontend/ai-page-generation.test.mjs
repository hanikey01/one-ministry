import test from 'node:test';
import assert from 'node:assert/strict';
import {waitForAiProposal} from '../../resources/js/ai-page-generation.js';

test('polls each stage and returns a proposal for explicit review', async () => {
    const states = ['planning', 'composing', 'compiling', 'completed'];
    const seen = [];
    const proposal = {title:'Welcome', document:{version:2, sections:[]}};
    const result = await waitForAiProposal({state:'queued', poll_url:'/generation'}, {
        fetchStatus:async (url) => {assert.equal(url, '/generation'); return {state:states.shift(), poll_url:url, proposal};},
        wait:async () => {}, onState:(state) => seen.push(state), isConnected:() => true,
    });
    assert.equal(result, proposal);
    assert.equal(seen.length, 4);
});

test('failure exposes a safe reason and trace reference', async () => {
    await assert.rejects(waitForAiProposal({state:'failed', failure_code:'provider_timeout', trace_id:'trace-1'}, {}), /timed out.*trace-1/);
});

test('cancelled work never returns a proposal', async () => {
    await assert.rejects(waitForAiProposal({state:'cancelled', proposal:{document:{version:2}}}, {}), /cancelled/);
});

test('navigation stops polling without applying background results', async () => {
    let fetched = false;
    await assert.rejects(waitForAiProposal({state:'planning'}, {fetchStatus:async () => {fetched = true;}, wait:async () => {}, onState:() => {}, isConnected:() => false}), /background/);
    assert.equal(fetched, false);
});

test('malformed completion never reaches proposal review', async () => {
    await assert.rejects(waitForAiProposal({state:'completed', proposal:{document:{version:1}}}, {}), /invalid/);
});
