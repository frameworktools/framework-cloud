import {
    DocumentTextIcon, PhotoIcon, PresentationChartLineIcon, TableCellsIcon, VariableIcon, BeakerIcon, DocumentIcon, CircleStackIcon,
} from '@heroicons/vue/20/solid';

// What each artifact kind is called and drawn with in the report builder
const kinds = {
    html: { label: 'Document', icon: DocumentTextIcon },
    pdf: { label: 'PDF', icon: DocumentTextIcon },
    image: { label: 'Figure', icon: PhotoIcon },
    chart: { label: 'Interactive chart', icon: PresentationChartLineIcon },
    table: { label: 'Table', icon: TableCellsIcon },
    data: { label: 'Data file', icon: CircleStackIcon },
    model: { label: 'Model', icon: VariableIcon },
    test: { label: 'Hypothesis test', icon: BeakerIcon },
    text: { label: 'Text file', icon: DocumentIcon },
    file: { label: 'File', icon: DocumentIcon },
};

export function artifactKind(kind) {
    return kinds[kind] || kinds.file;
}
