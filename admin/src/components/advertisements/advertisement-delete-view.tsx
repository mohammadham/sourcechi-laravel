import ConfirmationCard from '@/components/common/confirmation-card';
import {
  useModalAction,
  useModalState,
} from '@/components/ui/modal/modal.context';
import { useDeleteAdvertisementMutation } from '@/data/advertisements';

const AdvertisementDeleteView = () => {
  const { mutate: deleteAdvertisement, isLoading: loading } =
    useDeleteAdvertisementMutation();

  const { data } = useModalState();
  const { closeModal } = useModalAction();

  function handleDelete() {
    deleteAdvertisement(Number(data));
    closeModal();
  }

  return (
    <ConfirmationCard
      onCancel={closeModal}
      onDelete={handleDelete}
      deleteBtnLoading={loading}
    />
  );
};

export default AdvertisementDeleteView;
