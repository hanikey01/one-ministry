const stages = {queued:'Starting the page designer…', planning:'Planning the visitor journey.', composing:'Composing native page elements.', compiling:'Validating the proposed design.'};
const failures = {provider_invalid_key:'The AI provider rejected its API key.', provider_rate_limited:'The AI provider is rate limited.', provider_timeout:'The AI provider timed out.', provider_schema_rejected:'The AI provider rejected the composition contract.', invalid_composition:'The generated design did not pass validation.', generation_failed:'Page generation failed. Check AI settings and operator diagnostics.'};

export async function waitForAiProposal(initial, {fetchStatus, wait, onState, isConnected}) {
    let generation = initial;
    while (!['completed', 'failed', 'cancelled'].includes(generation.state)) {
        if (!Object.hasOwn(stages, generation.state)) throw new Error('Unexpected generation state.');
        onState(stages[generation.state]);
        await wait();
        if (!isConnected()) throw new Error('Generation continues in the background.');
        generation = await fetchStatus(generation.poll_url);
    }
    if (generation.state === 'cancelled') throw new Error('Generation cancelled.');
    if (generation.state === 'failed') throw new Error(`${failures[generation.failure_code] ?? 'The configured models could not complete this design.'} Reference: ${generation.trace_id}.`);
    if (!generation.proposal?.document || generation.proposal.document.version !== 2) throw new Error('The completed proposal is invalid.');

    return generation.proposal;
}
