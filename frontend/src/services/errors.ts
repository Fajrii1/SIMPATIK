interface ApiErrorShape {
  response?: {
    data?: {
      message?: string;
      errors?: Record<string, string[]>;
    };
  };
  message?: string;
}

/**
 * Ambil pesan kesalahan pertama dari respons backend, lalu pesan umum, lalu cadangan.
 */
export const getApiErrorMessage = (error: unknown, fallback: string): string => {
  const maybeError = error as ApiErrorShape;
  const validationErrors = maybeError?.response?.data?.errors;
  const firstValidationError = validationErrors
    ? Object.values(validationErrors).flat()[0]
    : undefined;

  return (
    firstValidationError ||
    maybeError?.response?.data?.message ||
    maybeError?.message ||
    fallback
  );
};
