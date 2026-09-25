export type WhatsAppProfileField =
    | 'profilePicture'
    | 'displayName'
    | 'about'
    | 'description'
    | 'address'
    | 'email'
    | 'websites'
    | 'category';

export type WhatsAppProfileCapabilities = {
    readableFields: WhatsAppProfileField[];
    editableFields: WhatsAppProfileField[];
    canRequestDisplayName: boolean;
};

export type WhatsAppProfileCategoryOption = {
    value: string;
    label: string;
};

export type WhatsAppProfileData = {
    profilePictureUrl: string | null;
    displayName: string | null;
    nameStatus: string | null;
    newDisplayName: string | null;
    newNameStatus: string | null;
    about: string | null;
    description: string | null;
    address: string | null;
    email: string | null;
    websites: string[];
    category: string | null;
};

export type WhatsAppProfileResult =
    | { state: 'ready'; data: WhatsAppProfileData }
    | { state: 'not_connected' }
    | { state: 'error'; message: string };
