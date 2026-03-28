import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconKey, IconPlus, IconShieldLock } from '@tabler/icons-react';
import React, { useMemo } from 'react';

export default function Index() {
    const { companies, credentials, generatedCredential, errors } = usePage().props;

    const { data, setData, post, processing } = useForm({
        company_id: companies[0]?.id ?? '',
        branch_id: companies[0]?.branches?.[0]?.id ?? '',
        source_module: 'inventory',
        client_name: '',
    });

    const availableBranches = useMemo(() => {
        const selectedCompany = companies.find((company) => Number(company.id) === Number(data.company_id));

        return selectedCompany?.branches ?? [];
    }, [companies, data.company_id]);

    const submit = (e) => {
        e.preventDefault();
        post(route('apps.integration-client-secrets.store'));
    };

    return (
        <>
            <Head title="Client Secret" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <Table.Card title="Generate Client Key & Secret" className="lg:col-span-2">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div className="flex flex-col gap-2">
                                <label className="text-gray-600 text-sm">Company</label>
                                <select
                                    className="w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800"
                                    value={data.company_id}
                                    onChange={(e) => {
                                        const companyId = Number(e.target.value);
                                        const selectedCompany = companies.find((company) => Number(company.id) === companyId);
                                        setData({
                                            ...data,
                                            company_id: companyId,
                                            branch_id: selectedCompany?.branches?.[0]?.id ?? '',
                                        });
                                    }}
                                >
                                    {companies.map((company) => (
                                        <option key={company.id} value={company.id}>{company.name}</option>
                                    ))}
                                </select>
                                {errors.company_id && <small className="text-xs text-red-500">{errors.company_id}</small>}
                            </div>

                            <div className="flex flex-col gap-2">
                                <label className="text-gray-600 text-sm">Branch</label>
                                <select
                                    className="w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800"
                                    value={data.branch_id}
                                    onChange={(e) => setData('branch_id', Number(e.target.value))}
                                >
                                    {availableBranches.length ? availableBranches.map((branch) => (
                                        <option key={branch.id} value={branch.id}>{branch.name}</option>
                                    )) : <option value="">Tidak ada branch aktif</option>}
                                </select>
                                {errors.branch_id && <small className="text-xs text-red-500">{errors.branch_id}</small>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <Input
                                label="Source Module"
                                type="text"
                                placeholder="Contoh: inventory"
                                value={data.source_module}
                                onChange={(e) => setData('source_module', e.target.value.toLowerCase())}
                                errors={errors.source_module}
                            />
                            <Input
                                label="Nama Client (Opsional)"
                                type="text"
                                placeholder="Contoh: POS Jakarta"
                                value={data.client_name}
                                onChange={(e) => setData('client_name', e.target.value)}
                                errors={errors.client_name}
                            />
                        </div>

                        <Button
                            type="submit"
                            variant="gray"
                            icon={<IconPlus size={18} strokeWidth={1.5} />}
                            label={processing ? 'Generating...' : 'Generate Credential'}
                            disabled={processing || !availableBranches.length || !companies.length}
                        />
                    </form>
                </Table.Card>

                <Table.Card title="Catatan" className="h-fit">
                    <div className="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                        <p>1. Secret hanya ditampilkan sekali setelah proses generate.</p>
                        <p>2. Simpan secret di tempat aman (vault/password manager).</p>
                        <p>3. Client key bisa dilihat kembali dari tabel histori.</p>
                    </div>
                </Table.Card>
            </div>

            {generatedCredential && (
                <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:bg-emerald-950/20 dark:border-emerald-900">
                    <div className="flex items-center gap-2 mb-2 text-emerald-700 dark:text-emerald-300 font-semibold">
                        <IconShieldLock size={18} strokeWidth={1.5} />
                        Credential berhasil dibuat
                    </div>
                    <div className="text-sm text-emerald-900 dark:text-emerald-200 space-y-1">
                        <p><span className="font-semibold">Client Key:</span> {generatedCredential.client_key}</p>
                        <p><span className="font-semibold">Client Secret:</span> {generatedCredential.client_secret}</p>
                    </div>
                </div>
            )}

            <Table.Card title="Daftar Client Credential">
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Client Name</Table.Th>
                            <Table.Th>Client Key</Table.Th>
                            <Table.Th>Source Module</Table.Th>
                            <Table.Th>Company / Branch</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th>Last Used</Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {credentials.data.map((credential, index) => (
                            <tr key={credential.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td>{index + 1 + ((credentials.current_page - 1) * credentials.per_page)}</Table.Td>
                                <Table.Td>{credential.client_name || '-'}</Table.Td>
                                <Table.Td className="font-mono text-xs">{credential.client_key}</Table.Td>
                                <Table.Td className="uppercase">{credential.source_module}</Table.Td>
                                <Table.Td>{credential.company?.name} / {credential.branch?.name}</Table.Td>
                                <Table.Td>{credential.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>{credential.last_used_at ? new Date(credential.last_used_at).toLocaleString('id-ID') : '-'}</Table.Td>
                            </tr>
                        ))}
                        {!credentials.data.length && (
                            <Table.Empty
                                colSpan={7}
                                message={<div className="flex items-center justify-center gap-2"><IconKey size={18} />Belum ada credential integration.</div>}
                            />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {credentials.last_page > 1 && <Pagination links={credentials.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
