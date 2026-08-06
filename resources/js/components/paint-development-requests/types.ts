export type StepProps = {
    data: {
        client_name: string;
        project_name: string;
        responsible: string;
        city: string;
        sample_due_date: string;
        current_product: string;
        context_payload: Record<string, unknown>;
        performance_payload: Record<string, unknown>;
        application_payload: Record<string, unknown>;
        specifications_payload: Record<string, unknown>;
    };
    setPayload: (payloadKey: string, field: string, value: unknown) => void;
    setData: (field: string, value: unknown) => void;
    errors: Record<string, string>;
};
