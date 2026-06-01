import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconDatabaseOff, IconPencilCog, IconPlus, IconTrash } from '@tabler/icons-react';
import React, { useMemo } from 'react';

const defaultLine = (lineNo = 1) => ({
    line_no: lineNo,
    line_side: lineNo % 2 === 0 ? 'credit' : 'debit',
    account_source_type: 'mapping',
    fixed_account_id: '',
    mapping_key: '',
    amount_source: 'payload_total',
    formula_json: {},
    dimension_rule_json: {},
    description_template: '',
});

const defaultMapping = () => ({
    mapping_key: '',
    account_id: '',
    description: '',
});

const buildInitialData = (companies) => ({
    id: '',
    company_id: companies[0]?.id ?? '',
    module_name: 'sales',
    event_name: '',
    transaction_type: '',
    rule_code: '',
    rule_name: '',
    version: 1,
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    priority: 100,
    is_active: true,
    description: '',
    lines: [defaultLine(1), defaultLine(2)],
    coa_mappings: [defaultMapping()],
    isUpdate: false,
    isOpen: false,
});

export default function Index() {
    const { rules, companies, chartOfAccounts, mappingByCompanyModule, presetTemplates = {}, errors } = usePage().props;
    const { data, setData, post, put, processing } = useForm(buildInitialData(companies));

    const availableAccounts = useMemo(
        () => chartOfAccounts.filter((coa) => Number(coa.company_id) === Number(data.company_id)),
        [chartOfAccounts, data.company_id]
    );

    const resetForm = () => setData(buildInitialData(companies));

    const openCreate = () => setData({ ...buildInitialData(companies), isOpen: true });

    const applyTemplate = (templateKey) => {
        const template = presetTemplates?.[templateKey]?.data;

        if (!template) {
            return;
        }

        setData({
            ...data,
            ...template,
            lines: template.lines.map((line) => ({
                ...defaultLine(line.line_no),
                account_source_type: 'mapping',
                fixed_account_id: '',
                dimension_rule_json: {},
                ...line,
                formula_json: line.formula_json ?? {},
                formula_json_text: line.formula_json ? JSON.stringify(line.formula_json) : '',
            })),
            coa_mappings: template.coa_mappings.map((mapping) => ({
                ...defaultMapping(),
                ...mapping,
            })),
        });
    };

    const openEdit = (rule) => {
        const key = `${rule.company_id}|${rule.module_name}`;
        const scopedMappings = mappingByCompanyModule?.[key] ?? [];
        const lineKeys = [...new Set((rule.lines ?? []).map((line) => line.mapping_key).filter(Boolean))];
        const mappings = scopedMappings
            .filter((mapping) => lineKeys.includes(mapping.mapping_key))
            .map((mapping) => ({
                mapping_key: mapping.mapping_key,
                account_id: mapping.account_id,
                description: mapping.description ?? '',
            }));

        setData({
            id: rule.id,
            company_id: rule.company_id,
            module_name: rule.module_name,
            event_name: rule.event_name,
            transaction_type: rule.transaction_type,
            rule_code: rule.rule_code,
            rule_name: rule.rule_name,
            version: rule.version,
            effective_from: rule.effective_from,
            effective_to: rule.effective_to ?? '',
            priority: rule.priority,
            is_active: Boolean(rule.is_active),
            description: rule.description ?? '',
            lines: (rule.lines ?? []).map((line) => ({
                line_no: line.line_no,
                line_side: line.line_side,
                account_source_type: line.account_source_type,
                fixed_account_id: line.fixed_account_id ?? '',
                mapping_key: line.mapping_key ?? '',
                amount_source: line.amount_source,
                formula_json: line.formula_json ?? {},
                formula_json_text: line.formula_json ? JSON.stringify(line.formula_json) : '',
                dimension_rule_json: line.dimension_rule_json ?? {},
                description_template: line.description_template ?? '',
            })),
            coa_mappings: mappings.length ? mappings : [defaultMapping()],
            isUpdate: true,
            isOpen: true,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const options = {
            onSuccess: resetForm,
        };

        if (data.isUpdate) {
            put(route('apps.preset-journals.update', data.id), options);
            return;
        }

        post(route('apps.preset-journals.store'), options);
    };

    const updateLine = (index, field, value) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], [field]: value };
        setData('lines', lines);
    };

    const updateFormulaJsonText = (index, value) => {
        const lines = [...data.lines];
        let parsed = lines[index].formula_json ?? {};

        try {
            parsed = value ? JSON.parse(value) : {};
        } catch (error) {
            parsed = lines[index].formula_json ?? {};
        }

        lines[index] = { ...lines[index], formula_json_text: value, formula_json: parsed };
        setData('lines', lines);
    };

    const updateMapping = (index, field, value) => {
        const mappings = [...data.coa_mappings];
        mappings[index] = { ...mappings[index], [field]: value };
        setData('coa_mappings', mappings);
    };

    return (
        <>
            <Head title="Preset Jurnal" />

            <div className="mb-4 flex justify-end">
                <Button type="button" variant="gray" icon={<IconPlus size={18} strokeWidth={1.5} />} label="Tambah Preset Jurnal" onClick={openCreate} />
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Edit Preset Jurnal' : 'Tambah Preset Jurnal'} maxWidth="6xl">
                <form onSubmit={submit} className="space-y-4">
                    {!data.isUpdate && Object.keys(presetTemplates).length > 0 && (
                        <div className="rounded-md border border-blue-100 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30">
                            <label className="text-sm font-semibold text-blue-700 dark:text-blue-300">Template Preset</label>
                            <select
                                className="mt-2 w-full rounded-md border border-blue-200 bg-white px-3 py-2 text-sm dark:border-blue-900 dark:bg-gray-900"
                                defaultValue=""
                                onChange={(e) => applyTemplate(e.target.value)}
                            >
                                <option value="">Pilih template preset jurnal</option>
                                {Object.entries(presetTemplates).map(([key, template]) => (
                                    <option key={key} value={key}>{template.label}</option>
                                ))}
                            </select>
                            <p className="mt-2 text-xs text-blue-700 dark:text-blue-300">Gunakan template Sales Invoice Posted untuk mapping AR, Diskon, Revenue, PPN setelah diskon, Ongkir, COGS, dan Inventory.</p>
                        </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div className="flex flex-col gap-2">
                            <label className="text-sm text-gray-600">Company</label>
                            <select
                                className="w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800"
                                value={data.company_id}
                                onChange={(e) => setData('company_id', Number(e.target.value))}
                            >
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>{company.name}</option>
                                ))}
                            </select>
                            {errors.company_id && <small className="text-xs text-red-500">{errors.company_id}</small>}
                        </div>
                        <Input label="Module Name" type="text" value={data.module_name} onChange={(e) => setData('module_name', e.target.value)} errors={errors.module_name} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <Input label="Rule Code" type="text" value={data.rule_code} onChange={(e) => setData('rule_code', e.target.value)} errors={errors.rule_code} />
                        <Input label="Rule Name" type="text" value={data.rule_name} onChange={(e) => setData('rule_name', e.target.value)} errors={errors.rule_name} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <Input label="Event Name" type="text" value={data.event_name} onChange={(e) => setData('event_name', e.target.value)} errors={errors.event_name} />
                        <Input label="Transaction Type" type="text" value={data.transaction_type} onChange={(e) => setData('transaction_type', e.target.value)} errors={errors.transaction_type} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <Input label="Version" type="number" value={data.version} onChange={(e) => setData('version', Number(e.target.value))} errors={errors.version} />
                        <Input label="Priority" type="number" value={data.priority} onChange={(e) => setData('priority', Number(e.target.value))} errors={errors.priority} />
                        <Input label="Effective From" type="date" value={data.effective_from} onChange={(e) => setData('effective_from', e.target.value)} errors={errors.effective_from} />
                        <Input label="Effective To" type="date" value={data.effective_to} onChange={(e) => setData('effective_to', e.target.value)} errors={errors.effective_to} />
                    </div>

                    <div>
                        <label className="text-sm text-gray-600">Description</label>
                        <textarea
                            className="w-full rounded-md border border-gray-200 p-2 text-sm dark:border-gray-800 dark:bg-gray-900"
                            rows={2}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </div>

                    <div className="rounded-md border border-gray-200 dark:border-gray-800 p-3 space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="font-semibold">Baris Preset Jurnal</h4>
                            <Button type="button" variant="gray" icon={<IconPlus size={14} />} label="Tambah Line" onClick={() => setData('lines', [...data.lines, defaultLine(data.lines.length + 1)])} />
                        </div>
                        {data.lines.map((line, index) => (
                            <div key={index} className="grid grid-cols-1 md:grid-cols-[5ch_minmax(7rem,0.75fr)_minmax(23ch,1.2fr)_minmax(10rem,1fr)_minmax(18ch,1.2fr)_5ch] gap-2 items-end rounded border border-gray-100 dark:border-gray-900 p-2">
                                <Input label="Line No" type="number" className="px-1 text-center" value={line.line_no} onChange={(e) => updateLine(index, 'line_no', Number(e.target.value))} />
                                <div>
                                    <label className="text-xs text-gray-500">Side</label>
                                    <select className="w-full px-3 py-1.5 border text-sm rounded-md" value={line.line_side} onChange={(e) => updateLine(index, 'line_side', e.target.value)}>
                                        <option value="debit">Debit</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                                <Input label="Mapping Key" type="text" value={line.mapping_key} onChange={(e) => updateLine(index, 'mapping_key', e.target.value)} />
                                <div>
                                    <label className="text-xs text-gray-500">Amount Source</label>
                                    <select className="w-full px-3 py-1.5 border text-sm rounded-md" value={line.amount_source} onChange={(e) => updateLine(index, 'amount_source', e.target.value)}>
                                        <option value="payload_total">payload_total</option>
                                        <option value="payload_tax">payload_tax</option>
                                        <option value="payload_net">payload_net</option>
                                        <option value="formula">formula</option>
                                    </select>
                                </div>
                                <Input label="Formula JSON" type="text" value={line.formula_json_text ?? (line.formula_json ? JSON.stringify(line.formula_json) : '')} onChange={(e) => updateFormulaJsonText(index, e.target.value)} />
                                <Button type="button" variant="rose" className="w-[5ch] justify-center px-0" icon={<IconTrash size={14} />} onClick={() => setData('lines', data.lines.filter((_, idx) => idx !== index))} />
                            </div>
                        ))}
                        {errors.lines && <small className="text-xs text-red-500">{errors.lines}</small>}
                    </div>

                    <div className="rounded-md border border-gray-200 dark:border-gray-800 p-3 space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="font-semibold">COA Mappings</h4>
                            <Button type="button" variant="gray" icon={<IconPlus size={14} />} label="Tambah Mapping" onClick={() => setData('coa_mappings', [...data.coa_mappings, defaultMapping()])} />
                        </div>
                        {data.coa_mappings.map((mapping, index) => (
                            <div key={index} className="grid grid-cols-1 md:grid-cols-[minmax(25ch,1.2fr)_minmax(25ch,1.4fr)_minmax(14rem,1fr)_5ch] gap-2 items-end rounded border border-gray-100 dark:border-gray-900 p-2">
                                <Input label="Mapping Key" type="text" value={mapping.mapping_key} onChange={(e) => updateMapping(index, 'mapping_key', e.target.value)} />
                                <div>
                                    <label className="text-xs text-gray-500">Account</label>
                                    <select
                                        className="w-full px-3 py-1.5 border text-sm rounded-md"
                                        value={mapping.account_id}
                                        onChange={(e) => updateMapping(index, 'account_id', Number(e.target.value))}
                                    >
                                        <option value="">Pilih Akun</option>
                                        {availableAccounts.map((coa) => (
                                            <option key={coa.id} value={coa.id}>{coa.code} - {coa.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <Input label="Description" type="text" value={mapping.description} onChange={(e) => updateMapping(index, 'description', e.target.value)} />
                                <Button type="button" variant="rose" className="w-[5ch] justify-center px-0" icon={<IconTrash size={14} />} onClick={() => setData('coa_mappings', data.coa_mappings.filter((_, idx) => idx !== index))} />
                            </div>
                        ))}
                        {errors.coa_mappings && <small className="text-xs text-red-500">{errors.coa_mappings}</small>}
                    </div>

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                            Active
                        </label>
                        <Button type="submit" variant="gray" label={processing ? 'Menyimpan...' : 'Simpan Preset'} disabled={processing} />
                    </div>
                </form>
            </Modal>

            <Table.Card title="Setup Preset Jurnal">
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Company</Table.Th>
                            <Table.Th>Rule</Table.Th>
                            <Table.Th>Event</Table.Th>
                            <Table.Th>Lines</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className="w-40"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {rules.data.map((rule, index) => (
                            <tr key={rule.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td>{index + 1 + ((rules.current_page - 1) * rules.per_page)}</Table.Td>
                                <Table.Td>{companies.find((company) => Number(company.id) === Number(rule.company_id))?.name || `#${rule.company_id}`}</Table.Td>
                                <Table.Td>
                                    <div className="font-semibold">{rule.rule_code}</div>
                                    <div className="text-xs text-gray-500">{rule.rule_name}</div>
                                </Table.Td>
                                <Table.Td>{rule.module_name} / {rule.event_name}</Table.Td>
                                <Table.Td>{rule.lines?.length ?? 0}</Table.Td>
                                <Table.Td>{rule.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        <Button type="modal" variant="orange" icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => openEdit(rule)} />
                                        <Button type="delete" variant="rose" icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.preset-journals.destroy', rule.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
                        ))}
                        {!rules.data.length && (
                            <Table.Empty colSpan={7} message={<div className="flex items-center justify-center gap-2"><IconDatabaseOff size={18} />Belum ada preset jurnal.</div>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>

            {rules.last_page > 1 && <Pagination links={rules.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
