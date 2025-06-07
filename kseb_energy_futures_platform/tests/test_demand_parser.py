import unittest
import pandas as pd
import os
from kseb_energy_futures_platform.modules.demand_projection.file_parser import load_input_demand_data

class TestDemandParser(unittest.TestCase):
    SAMPLE_FILE_DIR = "sample_data" # Relative to tests directory
    SAMPLE_FILE_PATH = os.path.join(SAMPLE_FILE_DIR, "sample_demand_input.xlsx")

    @classmethod
    def setUpClass(cls):
        """Create a sample Excel file for testing demand data parsing."""
        # Ensure sample_data directory exists
        if not os.path.exists(cls.SAMPLE_FILE_DIR):
            os.makedirs(cls.SAMPLE_FILE_DIR)

        try:
            with pd.ExcelWriter(cls.SAMPLE_FILE_PATH, engine='openpyxl') as writer:
                # Main sheet
                main_data_rows = [
                    ["~Settings Table", "", ""], # Use empty strings instead of None for clarity
                    ["Setting", "Value", "Notes"],
                    ["Start_Year", 2019, ""],
                    ["End_Year", 2021, ""],
                    ["Econometric_Parameters", "No", "e.g. Yes/No"],
                    ["Forecast_Model", "ARIMA", "e.g. ARIMA, Regression"],
                    ["", "", ""], # Empty row
                    ["~Consumption_Sectors Table", "", ""],
                    ["Sector_Name", "Description", "Unit"],
                    ["Residential", "Housing units consumption", "GWh"],
                    ["Commercial", "Commercial buildings consumption", "GWh"],
                    ["Industrial", "Industrial sector consumption", "GWh"],
                    ["", "", ""], # Empty row to end table
                ]
                pd.DataFrame(main_data_rows).to_excel(writer, sheet_name="main", index=False, header=False)

                # Residential sheet
                res_data = {'Year': [2019, 2020, 2021], 'Electricity': [100, 110, 120], 'Other_Fuel': [10,11,12]}
                pd.DataFrame(res_data).to_excel(writer, sheet_name="Residential", index=False)

                # Commercial sheet
                com_data = {'Year': [2019, 2020, 2021], 'Electricity': [50, 55, 60]}
                pd.DataFrame(com_data).to_excel(writer, sheet_name="Commercial", index=False)

                # Industrial sheet (with a missing year to test aggregation robustness)
                ind_data = {'Year': [2019, 2021], 'Electricity': [200, 220]}
                pd.DataFrame(ind_data).to_excel(writer, sheet_name="Industrial", index=False)

            print(f"Created sample file: {cls.SAMPLE_FILE_PATH}")
        except Exception as e:
            print(f"Error creating sample Excel file for demand parser: {e}")
            # If file creation fails, tests might not run correctly.
            # Consider raising an exception or ensuring tests handle file absence.
            raise

    @classmethod
    def tearDownClass(cls):
        """Remove the sample Excel file after tests."""
        if os.path.exists(cls.SAMPLE_FILE_PATH):
            os.remove(cls.SAMPLE_FILE_PATH)
            print(f"Removed sample file: {cls.SAMPLE_FILE_PATH}")
        # Attempt to remove sample_data dir if empty, not critical if it fails
        try:
            if not os.listdir(cls.SAMPLE_FILE_DIR): # Check if dir is empty
                os.rmdir(cls.SAMPLE_FILE_DIR)
                print(f"Removed directory: {cls.SAMPLE_FILE_DIR}")
        except OSError:
            print(f"Warning: Could not remove directory {cls.SAMPLE_FILE_DIR}. It might not be empty or permissions issue.")


    def test_load_input_demand_data_structure_and_settings(self):
        """Test the basic structure and settings extraction from the sample demand file."""
        parsed_data = load_input_demand_data(self.SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data, "Parser returned None, expected a dictionary.")

        expected_keys = ['settings', 'sectors_list', 'econometric_map',
                         'economic_indicators_raw', 'sector_data', 'aggregated_electricity']
        for key in expected_keys:
            self.assertIn(key, parsed_data, f"Key '{key}' missing in parsed data.")

        # Test settings
        settings = parsed_data['settings']
        self.assertEqual(settings.get('Start_Year'), 2019)
        self.assertEqual(settings.get('End_Year'), 2021)
        self.assertEqual(settings.get('Econometric_Parameters'), 'No') # Defaulted to No if not Yes
        self.assertEqual(settings.get('Forecast_Model'), 'ARIMA')

        # Test sectors list
        expected_sectors = ['Residential', 'Commercial', 'Industrial']
        self.assertListEqual(sorted(parsed_data['sectors_list']), sorted(expected_sectors))

        # Test sector_data keys
        for sector in expected_sectors:
            self.assertIn(sector, parsed_data['sector_data'], f"Data for sector '{sector}' missing.")
            self.assertIsInstance(parsed_data['sector_data'][sector], pd.DataFrame)

    def test_sector_data_content(self):
        """Test content of individual sector DataFrames."""
        parsed_data = load_input_demand_data(self.SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data)

        residential_df = parsed_data['sector_data'].get('Residential')
        self.assertIsNotNone(residential_df)
        self.assertEqual(len(residential_df), 3)
        self.assertTrue(all(residential_df[residential_df['Year'] == 2019]['Electricity'] == 100))

        commercial_df = parsed_data['sector_data'].get('Commercial')
        self.assertIsNotNone(commercial_df)
        self.assertEqual(len(commercial_df), 3)
        self.assertTrue(all(commercial_df[commercial_df['Year'] == 2020]['Electricity'] == 55))

        industrial_df = parsed_data['sector_data'].get('Industrial')
        self.assertIsNotNone(industrial_df)
        self.assertEqual(len(industrial_df), 2) # Only 2 years of data
        self.assertTrue(all(industrial_df[industrial_df['Year'] == 2021]['Electricity'] == 220))


    def test_load_input_demand_data_aggregation(self):
        """Test the aggregation of electricity consumption data."""
        parsed_data = load_input_demand_data(self.SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data)

        agg_df = parsed_data['aggregated_electricity']
        self.assertIsInstance(agg_df, pd.DataFrame)

        expected_agg_cols = ['Year', 'Residential_Electricity', 'Commercial_Electricity', 'Industrial_Electricity', 'Total_Electricity']
        for col in expected_agg_cols:
            self.assertIn(col, agg_df.columns, f"Column '{col}' missing in aggregated_electricity DataFrame.")

        # Check data for a specific year (2019)
        row_2019 = agg_df[agg_df['Year'] == 2019]
        self.assertEqual(len(row_2019), 1, "Should have one row for Year 2019 in aggregated data.")
        self.assertEqual(row_2019.iloc[0]['Residential_Electricity'], 100)
        self.assertEqual(row_2019.iloc[0]['Commercial_Electricity'], 50)
        self.assertEqual(row_2019.iloc[0]['Industrial_Electricity'], 200)
        self.assertEqual(row_2019.iloc[0]['Total_Electricity'], 350) # 100 + 50 + 200

        # Check data for a year where one sector might be missing (2020 for Industrial)
        row_2020 = agg_df[agg_df['Year'] == 2020]
        self.assertEqual(len(row_2020), 1)
        self.assertEqual(row_2020.iloc[0]['Residential_Electricity'], 110)
        self.assertEqual(row_2020.iloc[0]['Commercial_Electricity'], 55)
        self.assertTrue(pd.isna(row_2020.iloc[0]['Industrial_Electricity']), "Industrial electricity for 2020 should be NaN due to missing data.")
        self.assertEqual(row_2020.iloc[0]['Total_Electricity'], 165) # 110 + 55 + 0 (NaN treated as 0 in sum)

    def test_econometric_parameters_handling_when_no(self):
        """Test that econometric parts are empty when flag is 'No'."""
        parsed_data = load_input_demand_data(self.SAMPLE_FILE_PATH) # Flag is 'No' in this file
        self.assertIsNotNone(parsed_data)
        self.assertEqual(parsed_data['settings'].get('Econometric_Parameters'), 'No')
        self.assertDictEqual(parsed_data['econometric_map'], {})
        self.assertTrue(parsed_data['economic_indicators_raw'].empty)

        # Check that sector_data does not contain eco columns
        residential_df = parsed_data['sector_data'].get('Residential')
        self.assertListEqual(list(residential_df.columns), ['Year', 'Electricity', 'Other_Fuel'])


if __name__ == '__main__':
    unittest.main()
