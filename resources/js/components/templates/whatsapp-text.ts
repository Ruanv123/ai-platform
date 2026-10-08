export type TextSegment = {
    text: string;
    kind:
        | 'plain'
        | 'bold'
        | 'italic'
        | 'strike'
        | 'mono'
        | 'variable'
        | 'marker';
};

const variablePattern = /{{\s*(\d+)\s*}}/g;

export function variablePositions(text: string): number[] {
    const positions = Array.from(text.matchAll(variablePattern), (match) =>
        Number(match[1]),
    );

    return Array.from(new Set(positions)).sort(
        (first, second) => first - second,
    );
}

export function fillVariables(
    text: string,
    examples: Record<number, string | undefined>,
): string {
    return text.replace(variablePattern, (token, position: string) => {
        const example = examples[Number(position)];

        return example ? example : `{{${position}}}`;
    });
}

export function removeVariable(text: string, position: number): string {
    return text
        .replace(new RegExp(` ?{{\\s*${position}\\s*}}`, 'g'), '')
        .replace(variablePattern, (token, current: string) => {
            const number = Number(current);

            return number > position ? `{{${number - 1}}}` : token;
        });
}

export function shiftExamples(
    examples: Record<number, string>,
    removedPosition: number,
): Record<number, string> {
    const shifted: Record<number, string> = {};

    Object.entries(examples).forEach(([position, example]) => {
        const number = Number(position);

        if (number < removedPosition) {
            shifted[number] = example;
        } else if (number > removedPosition) {
            shifted[number - 1] = example;
        }
    });

    return shifted;
}

export function nextVariablePosition(text: string): number {
    const positions = variablePositions(text);

    return positions.length === 0 ? 1 : positions[positions.length - 1] + 1;
}

export function formattedSegments(text: string): TextSegment[] {
    const segments: TextSegment[] = [];
    const pattern = /```([\s\S]+?)```|\*([^*\n]+)\*|_([^_\n]+)_|~([^~\n]+)~/g;
    let last = 0;

    for (const match of text.matchAll(pattern)) {
        const index = match.index ?? 0;

        if (index > last) {
            segments.push({ text: text.slice(last, index), kind: 'plain' });
        }

        if (match[1] !== undefined) {
            segments.push({ text: match[1], kind: 'mono' });
        } else if (match[2] !== undefined) {
            segments.push({ text: match[2], kind: 'bold' });
        } else if (match[3] !== undefined) {
            segments.push({ text: match[3], kind: 'italic' });
        } else {
            segments.push({ text: match[4], kind: 'strike' });
        }

        last = index + match[0].length;
    }

    if (last < text.length) {
        segments.push({ text: text.slice(last), kind: 'plain' });
    }

    return segments;
}

export function editorSegments(text: string): TextSegment[] {
    const segments: TextSegment[] = [];
    let last = 0;

    for (const match of text.matchAll(/{{\s*\d+\s*}}|```|[*_~]/g)) {
        const index = match.index ?? 0;

        if (index > last) {
            segments.push({ text: text.slice(last, index), kind: 'plain' });
        }

        segments.push({
            text: match[0],
            kind: match[0].startsWith('{') ? 'variable' : 'marker',
        });
        last = index + match[0].length;
    }

    if (last < text.length) {
        segments.push({ text: text.slice(last), kind: 'plain' });
    }

    return segments;
}
