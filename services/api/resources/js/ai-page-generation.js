const stages = {queued:'Starting the page designer.', planning:'Defining the page art direction.', composing:'Composing page sections.', reviewing:'Reviewing design quality and originality.', repairing:'Refining the weakest design section.', compiling:'Validating the proposed design.'};
const failures = {
    provider_invalid_key:'The AI provider rejected its API key.',
    provider_not_ready:'The configured model is unavailable or has not passed the Page Editor compatibility check.',
    provider_rate_limited:'The AI provider is rate limited.',
    provider_timeout:'The AI provider timed out.',
    provider_connection_failed:'The Page Editor could not connect to the AI provider.',
    provider_unavailable:'The AI provider is temporarily unavailable.',
    provider_schema_rejected:'The AI provider rejected the composition contract.',
    structured_output_failed:'The model did not return valid structured output.',
    provider_malformed_json:'The model returned malformed JSON that the Page Editor could not read.',
    output_truncated:'The model response ended before the design was complete.',
    invalid_design_plan:'The model returned an invalid page art-direction plan.',
    incomplete_design_plan:'The model planned fewer sections than this page requires.',
    model_output_budget_insufficient:'The configured model output limit is too small for Page Editor designs.',
    route_attempts_exhausted:'No usable primary or fallback model remains on the configured Page Editor route.',
    invalid_draft_shape:'The model returned an invalid page composition.',
    invalid_draft_document:'The generated page document could not be normalized.',
    invalid_section_batch:'The model returned an incomplete section group.',
    invalid_design_review:'The design-quality review returned invalid structured output.',
    invalid_composition:'The generated design did not pass validation.',
    canvas_destination_not_approved:'The model created an unapproved link destination.',
    unsafe_canvas_html:'The model returned unsafe or unsupported HTML.',
    unsafe_canvas_css:'The model returned unsafe or unsupported CSS.',
    insufficient_canvas_css:'The model did not provide complete responsive section styling.',
    invalid_canvas_editable_markers:'The model returned invalid editable text markers.',
    invalid_canvas_media_markers:'The model returned invalid media markers.',
    invalid_canvas_marker_key:'The model returned an invalid editable marker name.',
    canvas_media_must_use_library:'The model referenced media outside the approved library.',
    unexpected_generation_failure:'The model response could not be processed by the Page Editor.',
    generation_failed:'Page generation failed. Check AI settings and operator diagnostics.',
};

export async function waitForAiProposal(initial, {fetchStatus, wait, onState, isConnected}) {
    let generation = initial;
    while (!['completed', 'failed', 'cancelled'].includes(generation.state)) {
        if (!Object.hasOwn(stages, generation.state)) throw new Error('Unexpected generation state.');
        const progress = generation.progress;
        const message = generation.state === 'composing' && progress?.total_sections
            ? `Composing section ${Math.min(progress.completed_sections + 1, progress.total_sections)} of ${progress.total_sections}.`
            : stages[generation.state];
        onState(message);
        await wait();
        if (!isConnected()) throw new Error('Generation continues in the background.');
        generation = await fetchStatus(generation.poll_url);
    }
    if (generation.state === 'cancelled') throw new Error('Generation cancelled.');
    if (generation.state === 'failed') {
        const lastFailure = generation.failure?.at(-1);
        const detail = lastFailure?.stage ? ` Failed during ${lastFailure.stage.replaceAll('_', ' ')}.` : '';
        throw new Error(`${failures[generation.failure_code] ?? 'The configured models could not complete this design.'}${detail} Reference: ${generation.trace_id}.`);
    }
    if (!generation.proposal?.document || generation.proposal.document.version !== 2) throw new Error('The completed proposal is invalid.');

    return generation.proposal;
}
