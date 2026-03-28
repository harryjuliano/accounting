import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { IconChecklist, IconEye, IconFileAnalytics, IconPlugConnected } from '@tabler/icons-react';
import { useMemo, useState } from 'react';

export default function IntegrationEventsIndex({ receivedEvents, statusSummary }) {
    const { errors } = usePage().props;
    const [selectedEvent, setSelectedEvent] = useState(null);

    const statusCards = [
        { title: 'Received', value: statusSummary.received ?? 0 },
        { title: 'Validated', value: statusSummary.validated ?? 0 },
        { title: 'Processed', value: statusSummary.processed ?? 0 },
        { title: 'Failed', value: statusSummary.failed ?? 0 },
    ];

    const payloadPreview = useMemo(() => {
        if (!selectedEvent) return '-';

        return JSON.stringify(selectedEvent.payload_json ?? {}, null, 2);
    }, [selectedEvent]);

    const handleValidate = (event) => {
        router.post(route('apps.integration-events.validate', event.id));
    };

    const handlePost = (event) => {
        router.post(route('apps.integration-events.post', event.id));
    };

    return (
        <>
            <Head title="Integration Event" />

            {errors.integration && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950/20 dark:border-red-900 dark:text-red-300">{errors.integration}</div>
            )}

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white dark:border-gray-800">
                <h1 className="text-2xl font-semibold">Integration Event Monitor</h1>
                <p className="mt-2 text-sm text-slate-200">
                    Pantau seluruh event integrasi yang diterima sistem beserta status prosesnya.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                {statusCards.map((item) => (
                    <Widget
                        key={item.title}
                        title={item.title}
                        subtitle="Jumlah event"
                        total={String(item.value)}
                        icon={<IconChecklist size={20} strokeWidth={1.5} />}
                        color="bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    />
                ))}
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4">
                <Table.Card title="Daftar Event Integrasi Diterima" icon={<IconFileAnalytics size={20} strokeWidth={1.5} />}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>ID</Table.Th>
                                <Table.Th>Module</Table.Th>
                                <Table.Th>Event</Table.Th>
                                <Table.Th>Doc</Table.Th>
                                <Table.Th>Processing Status</Table.Th>
                                <Table.Th className="text-center">Open Failure</Table.Th>
                                <Table.Th>Error</Table.Th>
                                <Table.Th className="w-56 text-center">Action</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {receivedEvents.length === 0 ? (
                                <Table.Empty colSpan={8} message="Belum ada event integrasi yang diterima." />
                            ) : (
                                receivedEvents.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td className="font-medium">#{item.id}</Table.Td>
                                        <Table.Td>{item.source_module}</Table.Td>
                                        <Table.Td>
                                            <code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.event_name}</code>
                                        </Table.Td>
                                        <Table.Td>{item.source_document_no ?? item.source_document_type ?? '-'}</Table.Td>
                                        <Table.Td>
                                            <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium capitalize text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                                                {item.processing_status}
                                            </span>
                                        </Table.Td>
                                        <Table.Td className="text-center">{item.open_failure_count}</Table.Td>
                                        <Table.Td className="max-w-[320px] truncate">{item.error_message ?? '-'}</Table.Td>
                                        <Table.Td>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="gray"
                                                    icon={<IconEye size={16} strokeWidth={1.5} />}
                                                    onClick={() => setSelectedEvent(item)}
                                                />
                                                <button
                                                    type="button"
                                                    className="rounded-md bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60"
                                                    onClick={() => handleValidate(item)}
                                                    disabled={item.processing_status !== 'received'}
                                                >
                                                    Validate
                                                </button>
                                                <button
                                                    type="button"
                                                    className="rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
                                                    onClick={() => handlePost(item)}
                                                    disabled={item.processing_status !== 'validated'}
                                                >
                                                    Post
                                                </button>
                                            </div>
                                        </Table.Td>
                                    </tr>
                                ))
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>

            <Modal show={Boolean(selectedEvent)} onClose={() => setSelectedEvent(null)} title={selectedEvent ? `Detail Event #${selectedEvent.id}` : 'Detail Event'}>
                {selectedEvent && (
                    <div className="space-y-3 text-sm text-gray-700 dark:text-gray-200">
                        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <p><span className="font-semibold">Module:</span> {selectedEvent.source_module}</p>
                            <p><span className="font-semibold">Event:</span> {selectedEvent.event_name}</p>
                            <p><span className="font-semibold">Doc:</span> {selectedEvent.source_document_no ?? selectedEvent.source_document_type ?? '-'}</p>
                            <p><span className="font-semibold">Status:</span> <span className="capitalize">{selectedEvent.processing_status}</span></p>
                            <p><span className="font-semibold">Event Datetime:</span> {selectedEvent.event_datetime ?? '-'}</p>
                            <p><span className="font-semibold">Processed At:</span> {selectedEvent.processed_at ?? '-'}</p>
                        </div>
                        <div>
                            <p className="mb-1 font-semibold">Payload JSON</p>
                            <pre className="max-h-80 overflow-auto rounded-md bg-gray-100 p-3 text-xs dark:bg-gray-900">{payloadPreview}</pre>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

IntegrationEventsIndex.layout = (page) => <AppLayout children={page} icon={<IconPlugConnected size={20} strokeWidth={1.5} />} />;
