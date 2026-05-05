import { useState, useRef, useCallback, useEffect } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Upload, FileText, Loader2, CheckCircle, AlertTriangle, ArrowLeft, ArrowRight, FolderTree, Newspaper, Image } from 'lucide-react';
import { wpImport } from '@/lib/api';

interface PreviewData {
  site_title: string;
  site_description: string;
  base_url: string;
  categories: number;
  pages: number;
  posts: number;
  attachments: number;
  warnings: string[];
}

interface ImportResult {
  categories: number;
  pages: number;
  posts: number;
  attachments: number;
  warnings: string[];
  errors: string[];
  skipped: { type: string; title: string }[];
}

interface StatusData {
  import_id: string;
  status: string;
  message: string;
  result: ImportResult | null;
  error: string | null;
}

type Step = 'upload' | 'preview' | 'options' | 'executing' | 'done';

export default function ImportPage() {
  const { siteId = '' } = useParams();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [step, setStep] = useState<Step>('upload');
  const [importId, setImportId] = useState('');
  const [filename, setFilename] = useState('');
  const [preview, setPreview] = useState<PreviewData | null>(null);
  const [status, setStatus] = useState<StatusData | null>(null);
  const [options, setOptions] = useState({
    import_categories: true,
    import_pages: true,
    import_posts: true,
    import_media: true,
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => wpImport.upload(siteId, file),
    onSuccess: (res) => {
      const data = res.data.data;
      setImportId(data.import_id);
      setFilename(data.filename);
      setStep('preview');
      previewMutation.mutate(data.import_id);
    },
  });

  const previewMutation = useMutation({
    mutationFn: (id: string) => wpImport.preview(siteId, id),
    onSuccess: (res) => {
      setPreview(res.data.data);
    },
  });

  const executeMutation = useMutation({
    mutationFn: () => wpImport.execute(siteId, importId, options),
    onSuccess: () => {
      setStep('executing');
    },
  });

  // Poll for status while executing
  useEffect(() => {
    if (step !== 'executing') return;

    const interval = setInterval(async () => {
      try {
        const res = await wpImport.status(siteId, importId);
        const data: StatusData = res.data.data;
        setStatus(data);

        if (data.status === 'completed' || data.status === 'failed') {
          clearInterval(interval);
          setStep('done');
        }
      } catch {
        // Keep polling
      }
    }, 2000);

    return () => clearInterval(interval);
  }, [step, siteId, importId]);

  const handleFiles = useCallback((files: FileList | null) => {
    if (!files || files.length === 0) return;
    uploadMutation.mutate(files[0]);
  }, [uploadMutation]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    handleFiles(e.dataTransfer.files);
  }, [handleFiles]);

  return (
    <div className="max-w-3xl mx-auto py-10 px-6">
      <div className="flex items-center gap-3 mb-8">
        <Link
          to={`/sites/${siteId}/pages`}
          className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
        >
          <ArrowLeft className="h-5 w-5" />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">WordPress Import</h1>
          <p className="mt-1 text-sm text-gray-500">Import content from a WordPress WXR export file</p>
        </div>
      </div>

      {/* Step indicator */}
      <div className="flex items-center gap-2 mb-8">
        {(['upload', 'preview', 'options', 'executing', 'done'] as Step[]).map((s, i) => {
          const labels = ['Upload', 'Preview', 'Options', 'Import', 'Done'];
          const stepOrder = ['upload', 'preview', 'options', 'executing', 'done'];
          const currentIdx = stepOrder.indexOf(step);
          const thisIdx = i;
          const isActive = thisIdx === currentIdx;
          const isComplete = thisIdx < currentIdx;

          return (
            <div key={s} className="flex items-center gap-2">
              {i > 0 && <div className={`w-8 h-0.5 ${isComplete ? 'bg-blue-500' : 'bg-gray-200'}`} />}
              <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium ${
                isActive ? 'bg-blue-100 text-blue-700' :
                isComplete ? 'bg-blue-500 text-white' :
                'bg-gray-100 text-gray-400'
              }`}>
                {isComplete && <CheckCircle className="h-3 w-3" />}
                {labels[i]}
              </div>
            </div>
          );
        })}
      </div>

      {/* Step 1: Upload */}
      {step === 'upload' && (
        <div
          onDrop={handleDrop}
          onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
          onDragLeave={(e) => { e.preventDefault(); setIsDragging(false); }}
          className={`rounded-xl border-2 border-dashed p-12 text-center transition-colors ${
            isDragging ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'
          }`}
        >
          <Upload className={`h-12 w-12 mx-auto mb-4 ${isDragging ? 'text-blue-500' : 'text-gray-400'}`} />
          <h3 className="text-lg font-medium text-gray-900 mb-2">Upload WordPress Export</h3>
          <p className="text-sm text-gray-500 mb-4">
            Drag and drop your WXR file here, or click to browse.<br />
            Export from WordPress: Tools &rarr; Export &rarr; All content
          </p>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={uploadMutation.isPending}
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {uploadMutation.isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Uploading...
              </>
            ) : (
              <>
                <FileText className="h-4 w-4" />
                Choose File
              </>
            )}
          </button>
          <input
            ref={fileInputRef}
            type="file"
            accept=".xml,.wxr"
            className="hidden"
            onChange={(e) => handleFiles(e.target.files)}
          />
          {uploadMutation.isError && (
            <div className="mt-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
              {(uploadMutation.error as any)?.response?.data?.message || 'Upload failed. Please check the file and try again.'}
            </div>
          )}
        </div>
      )}

      {/* Step 2: Preview */}
      {step === 'preview' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 className="font-semibold text-gray-900">Import Preview</h3>
            <p className="text-sm text-gray-500 mt-0.5">{filename}</p>
          </div>

          {previewMutation.isPending && (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
              <span className="ml-3 text-gray-500">Analyzing export file...</span>
            </div>
          )}

          {previewMutation.isError && (
            <div className="p-6">
              <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                Failed to analyze the file. It may not be a valid WordPress export.
              </div>
            </div>
          )}

          {preview && (
            <div className="p-6">
              {preview.site_title && (
                <div className="mb-6">
                  <p className="text-sm text-gray-500">Source site</p>
                  <p className="font-medium text-gray-900">{preview.site_title}</p>
                  {preview.site_description && (
                    <p className="text-sm text-gray-500">{preview.site_description}</p>
                  )}
                </div>
              )}

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <StatCard icon={FolderTree} label="Categories" count={preview.categories} />
                <StatCard icon={FileText} label="Pages" count={preview.pages} />
                <StatCard icon={Newspaper} label="Posts" count={preview.posts} />
                <StatCard icon={Image} label="Attachments" count={preview.attachments} />
              </div>

              {preview.warnings.length > 0 && (
                <div className="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-6">
                  <div className="flex items-start gap-2">
                    <AlertTriangle className="h-4 w-4 text-amber-500 mt-0.5 shrink-0" />
                    <div className="text-sm text-amber-800">
                      {preview.warnings.map((w, i) => <p key={i}>{w}</p>)}
                    </div>
                  </div>
                </div>
              )}

              <div className="flex justify-end">
                <button
                  onClick={() => setStep('options')}
                  className="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                >
                  Continue
                  <ArrowRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Step 3: Options */}
      {step === 'options' && preview && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 className="font-semibold text-gray-900">Import Options</h3>
            <p className="text-sm text-gray-500 mt-0.5">Choose what to import</p>
          </div>

          <div className="p-6 space-y-4">
            <OptionCheckbox
              label="Categories"
              description={`${preview.categories} categories with hierarchy`}
              checked={options.import_categories}
              onChange={(v) => setOptions({ ...options, import_categories: v })}
              disabled={preview.categories === 0}
            />
            <OptionCheckbox
              label="Pages"
              description={`${preview.pages} pages with content blocks`}
              checked={options.import_pages}
              onChange={(v) => setOptions({ ...options, import_pages: v })}
              disabled={preview.pages === 0}
            />
            <OptionCheckbox
              label="Posts"
              description={`${preview.posts} blog posts with categories`}
              checked={options.import_posts}
              onChange={(v) => setOptions({ ...options, import_posts: v })}
              disabled={preview.posts === 0}
            />
            <OptionCheckbox
              label="Media / Attachments"
              description={`${preview.attachments} files will be downloaded and re-uploaded`}
              checked={options.import_media}
              onChange={(v) => setOptions({ ...options, import_media: v })}
              disabled={preview.attachments === 0}
            />
          </div>

          <div className="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
            <button
              onClick={() => setStep('preview')}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg"
            >
              <ArrowLeft className="h-4 w-4" />
              Back
            </button>
            <button
              onClick={() => executeMutation.mutate()}
              disabled={executeMutation.isPending}
              className="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {executeMutation.isPending ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Starting...
                </>
              ) : (
                <>
                  Start Import
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </button>
          </div>
        </div>
      )}

      {/* Step 4: Executing — detailed progress */}
      {step === 'executing' && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-blue-50">
            <div className="flex items-center gap-3">
              <Loader2 className="h-5 w-5 animate-spin text-blue-600" />
              <h3 className="font-semibold text-blue-900">Importing WordPress Content</h3>
            </div>
          </div>

          <div className="p-6">
            {/* Progress bar */}
            <div className="mb-4">
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm font-medium text-gray-700">
                  {status?.message || 'Preparing import...'}
                </span>
                <span className="text-sm font-bold text-blue-600">
                  {(status as any)?.progress ?? 0}%
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-3">
                <div
                  className="bg-blue-500 h-3 rounded-full transition-all duration-500 ease-out"
                  style={{ width: `${(status as any)?.progress ?? 0}%` }}
                />
              </div>
            </div>

            {/* Step indicators */}
            <div className="space-y-2 mt-6">
              {[
                { key: 'parsing', label: 'Parsing export file', icon: '1' },
                { key: 'categories', label: 'Importing categories', icon: '2' },
                { key: 'media', label: 'Downloading media files', icon: '3' },
                { key: 'pages', label: 'Importing pages', icon: '4' },
                { key: 'posts', label: 'Importing posts', icon: '5' },
              ].map((s) => {
                const currentStep = (status as any)?.step || 'init';
                const stepOrder = ['init', 'parsing', 'categories', 'media', 'pages', 'posts', 'done'];
                const currentIdx = stepOrder.indexOf(currentStep);
                const thisIdx = stepOrder.indexOf(s.key);
                const isComplete = thisIdx < currentIdx;
                const isCurrent = thisIdx === currentIdx;

                return (
                  <div key={s.key} className="flex items-center gap-3">
                    <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                      isComplete ? 'bg-green-500 text-white' :
                      isCurrent ? 'bg-blue-500 text-white animate-pulse' :
                      'bg-gray-200 text-gray-400'
                    }`}>
                      {isComplete ? <CheckCircle className="h-4 w-4" /> : s.icon}
                    </div>
                    <span className={`text-sm ${
                      isComplete ? 'text-green-700 font-medium' :
                      isCurrent ? 'text-blue-700 font-medium' :
                      'text-gray-400'
                    }`}>
                      {s.label}
                      {isCurrent && (status as any)?.counts && (
                        <span className="ml-2 text-xs text-blue-500">
                          {Object.entries((status as any).counts).map(([k, v]) => `${v} ${k}`).join(', ')}
                        </span>
                      )}
                    </span>
                  </div>
                );
              })}
            </div>

            <p className="mt-6 text-xs text-gray-400 text-center">
              Do not close this page. Large imports may take several minutes.
            </p>
          </div>
        </div>
      )}

      {/* Step 5: Done */}
      {step === 'done' && status && (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div className={`px-6 py-4 border-b ${
            status.status === 'completed' ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'
          }`}>
            <div className="flex items-center gap-2">
              {status.status === 'completed' ? (
                <CheckCircle className="h-5 w-5 text-green-600" />
              ) : (
                <AlertTriangle className="h-5 w-5 text-red-600" />
              )}
              <h3 className={`font-semibold ${
                status.status === 'completed' ? 'text-green-900' : 'text-red-900'
              }`}>
                {status.status === 'completed' ? 'Import Complete' : 'Import Failed'}
              </h3>
            </div>
          </div>

          <div className="p-6">
            {status.status === 'failed' && status.error && (
              <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 mb-6">
                {status.error}
              </div>
            )}

            {status.result && (
              <>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                  <StatCard icon={FolderTree} label="Categories" count={status.result.categories} />
                  <StatCard icon={FileText} label="Pages" count={status.result.pages} />
                  <StatCard icon={Newspaper} label="Posts" count={status.result.posts} />
                  <StatCard icon={Image} label="Attachments" count={status.result.attachments} />
                </div>

                {status.result.warnings.length > 0 && (
                  <div className="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-4">
                    <p className="text-sm font-medium text-amber-800 mb-1">Warnings</p>
                    {status.result.warnings.map((w, i) => (
                      <p key={i} className="text-sm text-amber-700">{w}</p>
                    ))}
                  </div>
                )}

                {status.result.errors.length > 0 && (
                  <div className="rounded-lg bg-red-50 border border-red-200 p-4 mb-4">
                    <p className="text-sm font-medium text-red-800 mb-1">Errors</p>
                    {status.result.errors.map((e, i) => (
                      <p key={i} className="text-sm text-red-700">{e}</p>
                    ))}
                  </div>
                )}

                {status.result.skipped.length > 0 && (
                  <div className="rounded-lg bg-gray-50 border border-gray-200 p-4 mb-4">
                    <p className="text-sm font-medium text-gray-700 mb-1">
                      Skipped ({status.result.skipped.length} items already existed)
                    </p>
                    <div className="max-h-32 overflow-y-auto">
                      {status.result.skipped.map((s, i) => (
                        <p key={i} className="text-sm text-gray-500">{s.type}: {s.title}</p>
                      ))}
                    </div>
                  </div>
                )}
              </>
            )}

            <div className="flex gap-3 mt-6">
              <Link
                to={`/sites/${siteId}/pages`}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
              >
                <FileText className="h-4 w-4" />
                View Pages
              </Link>
              <Link
                to={`/sites/${siteId}/posts`}
                className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
              >
                <Newspaper className="h-4 w-4" />
                View Posts
              </Link>
              <button
                onClick={() => {
                  setStep('upload');
                  setImportId('');
                  setPreview(null);
                  setStatus(null);
                }}
                className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
              >
                Import Another
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function StatCard({ icon: Icon, label, count }: { icon: any; label: string; count: number }) {
  return (
    <div className="bg-gray-50 rounded-lg p-4 text-center">
      <Icon className="h-6 w-6 text-gray-400 mx-auto mb-1" />
      <p className="text-2xl font-bold text-gray-900">{count}</p>
      <p className="text-xs text-gray-500">{label}</p>
    </div>
  );
}

function OptionCheckbox({
  label,
  description,
  checked,
  onChange,
  disabled,
}: {
  label: string;
  description: string;
  checked: boolean;
  onChange: (v: boolean) => void;
  disabled: boolean;
}) {
  return (
    <label className={`flex items-start gap-3 p-3 rounded-lg border transition-colors cursor-pointer ${
      disabled ? 'opacity-50 cursor-not-allowed border-gray-100 bg-gray-50' :
      checked ? 'border-blue-200 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
    }`}>
      <input
        type="checkbox"
        checked={checked && !disabled}
        onChange={(e) => !disabled && onChange(e.target.checked)}
        disabled={disabled}
        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
      />
      <div>
        <p className="text-sm font-medium text-gray-900">{label}</p>
        <p className="text-xs text-gray-500">{description}</p>
      </div>
    </label>
  );
}
